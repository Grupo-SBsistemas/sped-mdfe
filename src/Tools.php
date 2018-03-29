<?php

namespace NFePHP\MDFe;

use NFePHP\Common\Files;
use NFePHP\Common\Signer;
use NFePHP\Common\UFList;
use NFePHP\Common\Dom\Dom;
use NFePHP\Common\Exception;
use NFePHP\Common\Certificate;
use NFePHP\Common\Dom\ValidXsd;
use NFePHP\Common\Strings\Strings;
use NFePHP\MDFe\Auxiliar\Response;
use NFePHP\Common\DateTime\DateTime;
use NFePHP\Common\Soap\SoapInterface;
use NFePHP\Common\LotNumber\LotNumber;
use NFePHP\MDFe\Common\Tools as CommonTools;
use NFePHP\Common\Exception\RuntimeException;
use NFePHP\Common\Exception\InvalidArgumentException;

/**
 * Classe principal para a comunicação com a SEFAZ
 *
 * @category  Library
 * @package   nfephp-org/sped-mdfe
 * @copyright 2008-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-mdfe for the canonical source repository
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 */
class Tools extends CommonTools
{



    /**
     * errrors
     *
     * @var string
     */
    public $errors = array();
    /**
     * soapDebug
     *
     * @var string
     */
    public $soapDebug = '';
    /**
     * urlPortal
     * Instância do WebService
     *
     * @var string
     */
    protected $urlPortal = 'http://www.portalfiscal.inf.br/mdfe';
    /**
     * aLastRetEvent
     *
     * @var array
     */
    private $aLastRetEvent = array();
    /**
     * @var string
     */
    protected $rootDir;

    /**
     * addProtocolo
     * Adiciona o protocolo de autorização de uso da MDFe
     * NOTA: exigência da SEFAZ, a MDFe somente é válida com o seu respectivo protocolo
     *
     * @param  string  $pathMDFefile
     * @param  string  $pathProtfile
     * @param  boolean $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function addProtocolo($pathMDFefile = '', $pathProtfile = '', $saveFile = false)
    {
        //carrega a MDFe
        $docmdfe = new Dom();
        if (file_exists($pathMDFefile)) {
            //carrega o XML pelo caminho do arquivo informado
            $docmdfe->loadXMLFile($pathMDFefile);
        } else {
            //carrega o XML pelo conteúdo
            $docmdfe->loadXMLString($pathMDFefile);
        }
        $nodemdfe = $docmdfe->getNode('MDFe', 0);
        if ($nodemdfe == '') {
            $msg = "O arquivo indicado como MDFe não é um xml de MDFe!";
            throw new Exception\RuntimeException($msg);
        }
        if ($docmdfe->getNode('Signature') == '') {
            $msg = "O MDFe não está assinado!";
            throw new Exception\RuntimeException($msg);
        }
        //carrega o protocolo
        $docprot = new Dom();
        if (file_exists($pathMDFefile)) {
            //carrega o XML pelo caminho do arquivo informado
            $docprot->loadXMLFile($pathProtfile);
        } else {
            //carrega o XML pelo conteúdo
            $docprot->loadXMLString($pathProtfile);
        }
        $nodeprots = $docprot->getElementsByTagName('protMDFe');
        if ($nodeprots->length == 0) {
            $msg = "O arquivo indicado não contêm um protocolo de autorização!";
            throw new Exception\RuntimeException($msg);
        }
        //carrega dados da MDFe
        $tpAmb = $docmdfe->getNodeValue('tpAmb');
        $anomes = date(
            'Ym',
            DateTime::convertSefazTimeToTimestamp($docmdfe->getNodeValue('dhEmi'))
        );
        $infMDFe = $docmdfe->getNode("infMDFe", 0);
        $versao = $infMDFe->getAttribute("versao");
        $chaveId = $infMDFe->getAttribute("Id");
        $chaveMDFe = preg_replace('/[^0-9]/', '', $chaveId);
        $digValueMDFe = $docmdfe->getNodeValue('DigestValue');
        //carrega os dados do protocolo
        for ($i = 0; $i < $nodeprots->length; $i++) {
            $nodeprot = $nodeprots->item($i);
            $protver = $nodeprot->getAttribute("versao");
            $chaveProt = $nodeprot->getElementsByTagName("chMDFe")->item(0)->nodeValue;
            $digValueProt = $nodeprot->getElementsByTagName("digVal")->item(0)->nodeValue;
            $infProt = $nodeprot->getElementsByTagName("infProt")->item(0);
            if ($digValueMDFe == $digValueProt && $chaveMDFe == $chaveProt) {
                break;
            }
        }
        if ($digValueMDFe != $digValueProt) {
            $msg = "Inconsistência! O DigestValue do MDFe não combina com o"
                . " do digVal do protocolo indicado!";
            throw new Exception\RuntimeException($msg);
        }
        if ($chaveMDFe != $chaveProt) {
            $msg = "O protocolo indicado pertence a outro MDFe. Os números das chaves não combinam !";
            throw new Exception\RuntimeException($msg);
        }
        //cria a MDFe processada com a tag do protocolo
        $procmdfe = new \DOMDocument('1.0', 'UTF-8');
        $procmdfe->formatOutput = false;
        $procmdfe->preserveWhiteSpace = false;
        //cria a tag mdfeProc
        $mdfeProc = $procmdfe->createElement('mdfeProc');
        $procmdfe->appendChild($mdfeProc);
        //estabele o atributo de versão
        $mdfeProcAtt1 = $mdfeProc->appendChild($procmdfe->createAttribute('versao'));
        $mdfeProcAtt1->appendChild($procmdfe->createTextNode($protver));
        //estabelece o atributo xmlns
        $mdfeProcAtt2 = $mdfeProc->appendChild($procmdfe->createAttribute('xmlns'));
        $mdfeProcAtt2->appendChild($procmdfe->createTextNode($this->urlPortal));
        //inclui a tag MDFe
        $node = $procmdfe->importNode($nodemdfe, true);
        $mdfeProc->appendChild($node);
        //cria tag protMDFe
        $protMDFe = $procmdfe->createElement('protMDFe');
        $mdfeProc->appendChild($protMDFe);
        //estabele o atributo de versão
        $protMDFeAtt1 = $protMDFe->appendChild($procmdfe->createAttribute('versao'));
        $protMDFeAtt1->appendChild($procmdfe->createTextNode($versao));
        //cria tag infProt
        $nodep = $procmdfe->importNode($infProt, true);
        $protMDFe->appendChild($nodep);
        //salva o xml como string em uma variável
        $procXML = $procmdfe->saveXML();
        //remove as informações indesejadas
        $procXML = Strings::clearProt($procXML);
        if ($saveFile) {
            $filename = "$chaveMDFe-protMDFe.xml";
            $this->zGravaFile(
                'mdfe',
                $tpAmb,
                $filename,
                $procXML,
                'enviadas'.DIRECTORY_SEPARATOR.'aprovadas',
                $anomes
            );
        }
        return $procXML;
    }

    /**
     * addCancelamento
     * Adiciona a tga de cancelamento a uma MDFe já autorizada
     * NOTA: não é requisito da SEFAZ, mas auxilia na identificação das MDFe que foram canceladas
     *
     * @param  string $pathMDFefile
     * @param  string $pathCancfile
     * @param  bool   $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function addCancelamento($pathMDFefile = '', $pathCancfile = '', $saveFile = false)
    {
        $procXML = '';
        //carrega a MDFe
        $docmdfe = new Dom();
        if (file_exists($pathMDFefile)) {
            //carrega o XML pelo caminho do arquivo informado
            $docmdfe->loadXMLFile($pathMDFefile);
        } else {
            //carrega o XML pelo conteúdo
            $docmdfe->loadXMLString($pathMDFefile);
        }
        $nodemdfe = $docmdfe->getNode('MDFe', 0);
        if ($nodemdfe == '') {
            $msg = "O arquivo indicado como MDFe não é um xml de MDFe!";
            throw new Exception\RuntimeException($msg);
        }
        $proMDFe = $docmdfe->getNode('protMDFe');
        if ($proMDFe == '') {
            $msg = "O MDFe não está protocolado ainda!!";
            throw new Exception\RuntimeException($msg);
        }
        $chaveMDFe = $proMDFe->getElementsByTagName('chMDFe')->item(0)->nodeValue;
        //$nProtMDFe = $proMDFe->getElementsByTagName('nProt')->item(0)->nodeValue;
        $tpAmb = $docmdfe->getNodeValue('tpAmb');
        $anomes = date(
            'Ym',
            DateTime::convertSefazTimeToTimestamp($docmdfe->getNodeValue('dhEmi'))
        );
        //carrega o cancelamento
        //pode ser um evento ou resultado de uma consulta com multiplos eventos
        $doccanc = new Dom();
        if (file_exists($pathCancfile)) {
            //carrega o XML pelo caminho do arquivo informado
            $doccanc->loadXMLFile($pathCancfile);
        } else {
            //carrega o XML pelo conteúdo
            $doccanc->loadXMLString($pathCancfile);
        }
        $retEvento = $doccanc->getElementsByTagName('retEventoMDFe')->item(0);
        $eventos = $retEvento->getElementsByTagName('infEvento');
        foreach ($eventos as $evento) {
            //evento
            $cStat = $evento->getElementsByTagName('cStat')->item(0)->nodeValue;
            $tpAmb = $evento->getElementsByTagName('tpAmb')->item(0)->nodeValue;
            $chaveEvento = $evento->getElementsByTagName('chMDFe')->item(0)->nodeValue;
            $tpEvento = $evento->getElementsByTagName('tpEvento')->item(0)->nodeValue;
            //$nProtEvento = $evento->getElementsByTagName('nProt')->item(0)->nodeValue;
            //verifica se conferem os dados
            //cStat = 135 ==> evento homologado
            //tpEvento = 110111 ==> Cancelamento
            //chave do evento == chave da NFe
            //protocolo do evento ==  protocolo da NFe
            if ($cStat == '135'
                && $tpEvento == '110111'
                && $chaveEvento == $chaveMDFe
            ) {
                $docmdfe->getElementsByTagName('cStat')->item(0)->nodeValue = '101';
                $docmdfe->getElementsByTagName('xMotivo')->item(0)->nodeValue = 'Cancelamento de MDF-e homologado';
                $procXML = $docmdfe->saveXML();
                //remove as informações indesejadas
                $procXML = Strings::clearProt($procXML);
                if ($saveFile) {
                    $filename = "$chaveMDFe-protMDFe.xml";
                    $this->zGravaFile(
                        'mdfe',
                        $tpAmb,
                        $filename,
                        $procXML,
                        'enviadas'.DIRECTORY_SEPARATOR.'aprovadas',
                        $anomes
                    );
                }
                break;
            }
        }
        return (string) $procXML;
    }


    /**
     * verificaValidade
     *
     * @param  string $pathXmlFile
     * @param  array  $aRetorno
     * @return boolean
     * @throws Exception\InvalidArgumentException
     */
    public function verificaValidade($pathXmlFile = '', &$aRetorno = array())
    {
        $aRetorno = array();
        if (!file_exists($pathXmlFile)) {
            $msg = "Arquivo não localizado!!";
            throw new Exception\InvalidArgumentException($msg);
        }
        //carrega a MDFe
        $xml = Files\FilesFolders::readFile($pathXmlFile);
        $this->oCertificate->verifySignature($xml, 'infMDFe');
        //obtem o chave da MDFe
        $docmdfe = new Dom();
        $docmdfe->loadXMLFile($pathXmlFile);
        $tpAmb = $docmdfe->getNodeValue('tpAmb');
        $chMDFe  = $docmdfe->getChave('infMDFe');
        $this->sefazConsultaChave($chMDFe, $tpAmb, $aRetorno);
        if ($aRetorno['cStat'] != '100') {
            return false;
        }
        return true;
    }

    /**
     * assina
     *
     * @param  string  $xml
     * @param  boolean $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function assina($xml = '', $saveFile = false)
    {
        return $this->assinaDoc($xml, 'mdfe', 'infMDFe', $saveFile);
    }
    
    /**
     * sefazEnviaLote
     *
     * @param    string $xml
     * @param    string $tpAmb
     * @param    string $idLote
     * @param    array  $aRetorno
     * @return   string
     * @throws   Exception\InvalidArgumentException
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazEnviaLote($xml, $idLote) 
    {
        if (empty($xml)) {
            $msg = "Pelo menos um MDFe deve ser informado.";
            throw new InvalidArgumentException($msg);
        }
        $sxml = preg_replace("/<\?xml.*\?>/", "", $xml);
        $sxml = str_replace("\r", '', $sxml);
        $servico = 'MDFeRecepcao';
        $this->servico(
            $servico,
            $this->config->siglaUF,
            $this->tpAmb
        );
        
        //montagem dos dados da mensagem SOAP
        $request = "<enviMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<idLote>$idLote</idLote>$sxml</enviMDFe>";
        $this->isValid($this->urlVersion, $request, 'enviMDFe');
        $this->lastRequest = $request;

        //montagem dos dados da mensagem SOAP
        $parameters = ['mdfeDadosMsg' => $request];
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";

        //envia a solicitação via SOAP
        $this->lastResponse = $this->sendRequest($body, $parameters);
        
        return $this->lastResponse;
    }

    /**
     * sefazConsultaRecibo
     *
     * @param    string $recibo
     * @param    string $tpAmb
     * @param    array  $aRetorno
     * @return   string
     * @throws   Exception\InvalidArgumentException
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazConsultaRecibo($recibo = '', $tpAmb = '2')
    {
        if ($recibo == '') {
            $msg = "Deve ser informado um recibo.";
            throw new InvalidArgumentException($msg);
        }

        if ($tpAmb == '') {
            $tpAmb = $this->tpAmb;
        }

        //carrega serviço
        $servico = 'MDFeRetRecepcao';
        $this->servico(
            $servico,
            $this->config->siglaUF,
            $tpAmb
        );

        $cons = "<consReciMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<tpAmb>$tpAmb</tpAmb>"
            . "<nRec>$recibo</nRec>"
            . "</consReciMDFe>";
        $this->isValid($this->urlVersion, $cons, 'consReciMDFe');
        $this->lastRequest = $cons;
        //montagem dos dados da mensagem SOAP
        $parameters = ['mdfeDadosMsg' => $cons];
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$cons</mdfeDadosMsg>";
        $this->lastResponse = $this->sendRequest($body, $parameters);

        return $this->lastResponse;
    }

    /**
     * sefazConsultaChave
     * Consulta o status da MDFe pela chave de 44 digitos
     *
     * @param    string $chave
     * @param    string $tpAmb
     * @param    array  $aRetorno
     * @return   string
     * @throws   Exception\InvalidArgumentException
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazConsultaChave($chave = '', $tpAmb = '2')
    {

        $siglaUF = $this->validKeyByUF($chave);
        if (emtpy($tpAmb)) {
            $tpAmb = $this->tpAmb;
        }
        //carrega serviço
        $servico = 'MDFeConsulta';
        $this->servico(
            $servico,
            $siglaUF,
            $tpAmb
        );
        $cons = "<consSitMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<tpAmb>$tpAmb</tpAmb>"
                . "<xServ>CONSULTAR</xServ>"
                . "<chMDFe>$chMDFe</chMDFe>"
                . "</consSitMDFe>";
        $this->isValid($this->urlVersion, $cons, 'consSitMDFe');
        $this->lastRequest = $cons;

        //montagem dos dados da mensagem SOAP
        $parameters = ['mdfeDadosMsg' => $cons];
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$cons</mdfeDadosMsg>";
        $this->lastResponse = $this->sendRequest($body, $parameters);
        
        return $this->lastResponse;
    }

    /**
     * sefazStatus
     * Verifica o status do serviço da SEFAZ
     * NOTA : Este serviço será removido no futuro, segundo da Receita/SEFAZ devido
     * ao excesso de mau uso !!!
     *
     * @param    string $siglaUF  sigla da unidade da Federação
     * @param    string $tpAmb    tipo de ambiente 1-produção e 2-homologação
     * @param    array  $aRetorno parametro passado por referencia contendo a resposta da consulta em um array
     * @return   mixed string XML do retorno do webservice, ou false se ocorreu algum erro
     * @throws   Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    public function sefazStatus($siglaUF = '', $tpAmb = null)
    {
        if (empty($tpAmb)) {
            $tpAmb = $this->tpAmb;
        }
        $ignoreContingency = true;

        if (empty($siglaUF)) {
            $siglaUF = $this->config->siglaUF;
            $ignoreContingency = false;
        }
        
        $servico = 'MDFeStatusServico';
        $this->checkContingencyForWebServices($servico);
        $this->servico(
            $servico,
            $siglaUF,
            $tpAmb,
            $ignoreContingency
        );
        
        if ($this->urlService == '') {
            $msg = "O status não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }
        $request = "<consStatServMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<tpAmb>$tpAmb</tpAmb>"
            . "<xServ>STATUS</xServ></consStatServMDFe>";

        $this->isValid($this->urlVersion, $request, 'consStatServMDFe');
        $this->lastRequest = $request;

        //montagem dos dados da mensagem SOAP
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";
        $parameters = ['mdfeDadosMsg' => $request];
        $this->lastResponse = $this->sendRequest($body, $parameters);
        return $this->lastResponse;
    }

    /**
     * sefazCancela
     *
     * @param  string $chave
     * @param  string $tpAmb
     * @param  string $xJust
     * @param  string $nProt
     * @param  array  $aRetorno
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function sefazCancela($chave, $xJust, $nProt) {
        
        $uf = $this->validKeyByUF($chave);
        $xJust = Strings::replaceSpecialsChars(
            substr(trim($xJust), 0, 255)
        );
        $tpEvento = 110111;
        $nSeqEvento = 1;
        $tagAdic = "<nProt>$nProt</nProt><xJust>$xJust</xJust>";

        return $this->sefazEvento(
            $uf,
            $chave,
            $tpEvento,
            $nSeqEvento,
            $tagAdic
        );
    }

    /**
     * sefazEncerra
     *
     * @param  string $chave
     * @param  string $tpAmb
     * @param  string $nProt
     * @param  string $cUF
     * @param  string $cMun
     * @param  array  $aRetorno
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function sefazEncerra(
        $chave = '',
        $nSeqEvento = '1',
        $nProt = '',
        $cUF = '',
        $cMun = ''
    ) {

        $siglaUF = $this->validKeyByUF($chave);

        if (empty($nProt)) {
            $msg = "Não foi passado o numero do protocolo!!";
            throw new InvalidArgumentException($msg);
        }

        //estabelece o codigo do tipo de evento ENCERRAMENTO
        $tpEvento = '110112';
        if (empty($nSeqEvento)) {
            $nSeqEvento = '1';
        }
        $dtEnc = date('Y-m-d');
        $tagAdic = "<evEncMDFe><descEvento>Encerramento</descEvento>"
                . "<nProt>$nProt</nProt><dtEnc>$dtEnc</dtEnc><cUF>$cUF</cUF>"
                . "<cMun>$cMun</cMun></evEncMDFe>";

        return $this->sefazEvento(
            $siglaUF,
            $chave,
            $tpEvento,
            $nSeqEvento
        );
    }

    /**
     * sefazIncluiCondutor
     *
     * @param  string $chave
     * @param  string $tpAmb
     * @param  string $nSeqEvento
     * @param  string $xNome
     * @param  string $cpf
     * @param  array  $aRetorno
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function sefazIncluiCondutor(
        $chave = '',
        $nSeqEvento = '1',
        $xNome = '',
        $cpf = ''
    ) {
        $siglaUF = $this->validKeyByUF($chave);
        //estabelece o codigo do tipo de evento Inclusão de condutor
        $tpEvento = '110114';
        //monta mensagem
        $tagAdic = "<evIncCondutorMDFe><descEvento>Inclusao Condutor</descEvento>"
                . "<condutor><xNome>$xNome</xNome><CPF>$cpf</CPF></condutor></evIncCondutorMDFe>";

        return $this->sefazEvento(
            $siglaUF, 
            $chave, 
            $tpEvento, 
            $nSeqEvento
        );
    }

    /**
     * sefazConsultaNaoEncerrados
     *
     * @param  string $tpAmb
     * @param  string $cnpj
     * @param  array  $aRetorno
     * @return string
     * @throws Exception\RuntimeException
     */
    public function sefazConsultaNaoEncerrados($cnpj = '')
    {
        if (empty($cnpj)) {
            $cnpj = $this->config->cnpj;
        }
        //carrega serviço
        $servico = 'MDFeConsNaoEnc';
        $this->servico(
            $servico,
            $this->config->siglaUF,
            $this->tpAmb
        );
        $request = "<consMDFeNaoEnc xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<tpAmb>$this->tpAmb</tpAmb>"
                . "<xServ>CONSULTAR NÃO ENCERRADOS</xServ><CNPJ>$cnpj</CNPJ></consMDFeNaoEnc>";
        $this->isValid($this->urlVersion, $request, 'consMDFeNaoEnc');
        $this->lastRequest = $request;
        //montagem dos dados da mensagem SOAP
        $parameters = ['mdfeDadosMsg' => $request];
        $body = "<mdefDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";
        return $this->sendRequest($body, $parameters);
    }
    

    /**
     * Send event to SEFAZ
     * @param string $uf
     * @param string $chave
     * @param int $tpEvento
     * @param int $nSeqEvento
     * @param string $tagAdic
     * @return string
     */
    public function sefazEvento(
        $uf,
        $chave,
        $tpEvento,
        $nSeqEvento = 1,
        $tagAdic = ''
    ) {
        $servico = 'RecepcaoEvento';
        $this->checkContingencyForWebServices($servico);
        $this->servico(
            $servico,
            $uf,
            $this->tpAmb,
            false
        );
        $ev = $this->tpEv($tpEvento);
        $aliasEvento = $ev->alias;
        $descEvento = $ev->desc;
        $cnpj = $this->config->cnpj;
        $dt = new \DateTime();
        $dhEvento = $dt->format('Y-m-d\TH:i:sP');
        $sSeqEvento = str_pad($nSeqEvento, 2, "0", STR_PAD_LEFT);
        $eventId = "ID".$tpEvento.$chave.$sSeqEvento;
        $cOrgao = UFList::getCodeByUF($uf);
        $request = "<eventoMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<infEvento Id=\"$eventId\">"
            . "<cOrgao>$cOrgao</cOrgao>"
            . "<tpAmb>$this->tpAmb</tpAmb>"
            . "<CNPJ>$cnpj</CNPJ>"
            . "<chMDFe>$chave</chMDFe>"
            . "<dhEvento>$dhEvento</dhEvento>"
            . "<tpEvento>$tpEvento</tpEvento>"
            . "<nSeqEvento>$nSeqEvento</nSeqEvento>"
            . "<verEvento>$this->urlVersion</verEvento>"
            . "<detEvento versao=\"$this->urlVersion\">"
            . "<descEvento>$descEvento</descEvento>"
            . "$tagAdic"
            . "</detEvento>"
            . "</infEvento>"
            . "</eventoMDFe>";
        //assinatura dos dados
        $request = Signer::sign(
            $this->certificate,
            $request,
            'infEvento',
            'Id',
            $this->algorithm,
            $this->canonical
        );
        $request = Strings::clearXmlString($request, true);
        $lote = $dt->format('YmdHis').rand(0, 9);
        $request = "<envEvento xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<idLote>$lote</idLote>"
            . $request
            . "</envEvento>";
        $this->isValid($this->urlVersion, $request, 'envEvento');
        $this->lastRequest = $request;
        $parameters = ['mdfeDadosMsg' => $request];
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";
        $this->lastResponse = $this->sendRequest($body, $parameters);
        return $this->lastResponse;
    }

    private function tpEv($tpEvento = '')
    {
        $std = new \stdClass();
        $std->alias = '';
        $std->desc = '';
        switch ($tpEvento) {
            case '110111':
                //cancelamento
                $std->alias = 'CancMDFe';
                $std->desc = 'Cancelamento';
                break;
            case '110112':
                //encerramento
                $std->alias = 'EncMDFe';
                $std->desc = 'Encerramento';
                break;
            case '110114':
                //inclusao do condutor
                $std->alias = 'EvIncCondut';
                $std->desc = 'Inclusao Condutor';
                break;
            default:
                $msg = "O código do tipo de evento informado não corresponde a "
                . "nenhum evento estabelecido.";
                throw new RuntimeException($msg);
        }
        return $std;
    }

}
