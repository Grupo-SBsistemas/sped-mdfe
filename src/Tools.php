<?php

namespace NFePHP\MDFe;

use NFePHP\Common\Files;
use NFePHP\Common\Signer;
use NFePHP\Common\UFList;
use NFePHP\Common\Dom\Dom;
use NFePHP\Common\Strings;
use NFePHP\Common\Exception;
use NFePHP\Common\Certificate;
use NFePHP\Common\Dom\ValidXsd;
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
     * Request authorization to issue MDFe in batch with one or more documents
     * @param array $aXml array of mdfe's xml
     * @param string $idLote lote number
     * @param bool $compactar flag to compress data with gzip
     * @return string soap response xml
     */
    public function sefazEnviaLote(
        $aXml,
        $idLote = '',
        $compactar = false,
        &$xmls = []
    ) {
        if (!is_array($aXml)) {
            throw new \InvalidArgumentException('Os XML dos MDFe devem ser passados em um array.');
        }
        $servico = 'MDFeRecepcao';
        $this->checkContingencyForWebServices($servico);
        if ($this->contingency->type != '') {
            //em modo de contingencia
            //esses xml deverão ser modificados e re-assinados e retornados
            //no parametro $xmls para serem armazenados pelo aplicativo
            //pois serão alterados
            foreach ($aXml as $doc) {
                //corrigir o xml para o tipo de contigência setado
                $xmls[] = $this->correctNFeForContingencyMode($doc);
            }
            $aXml = $xmls;
        }

        $sxml = implode("", $aXml);
        $sxml = preg_replace("/<\?xml.*?\?>/", "", $sxml);
        $this->servico(
            $servico,
            $this->config->siglaUF,
            $this->tpAmb
        );

        $request = "<enviMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<idLote>$idLote</idLote>"
            . "$sxml"
            . "</enviMDFe>";
        $this->isValid($this->urlVersion, $request, 'enviMDFe');
        $this->lastRequest = $request;

        //montagem dos dados da mensagem SOAP
        $parameters = ['mdfeDadosMsg' => $request];
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";

        $method = $this->urlMethod;
        if ($compactar) {
            $gzdata = base64_encode(gzencode($cons, 9, FORCE_GZIP));
            $body = "<mdfeDadosMsgZip xmlns=\"$this->urlNamespace\">$gzdata</mdfeDadosMsgZip>";
            $method = $this->urlMethod."Zip";
            $parameters = ['mdfeDadosMsgZip' => $gzdata];
            $body = "<mdfeDadosMsgZip xmlns=\"$this->urlNamespace\">$gzdata</mdfeDadosMsgZip>";
        }

        $this->lastResponse = $this->sendRequest($body, $parameters);
        return $this->lastResponse;
    }

    /**
     * Check status of Batch of MDFe sent by receipt of this shipment
     * @param string $recibo
     * @param int $tpAmb
     * @return string
     */
    public function sefazConsultaRecibo($recibo, $tpAmb = null)
    {
        if (empty($tpAmb)) {
            $tpAmb = $this->tpAmb;
        }
        //carrega serviço
        $servico = 'MDFeRetRecepcao';
        $this->checkContingencyForWebServices($servico);
        $this->servico(
            $servico,
            $this->config->siglaUF,
            $tpAmb
        );

        if ($this->urlService == '') {
            $msg = "A consulta de MDFe não está disponível na SEFAZ {$this->config->siglaUF}!!!";
            throw new RuntimeException($msg);
        }

        $request = "<consReciMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . "<tpAmb>$tpAmb</tpAmb>"
            . "<nRec>$recibo</nRec>"
            . "</consReciMDFe>";

        $this->isValid($this->urlVersion, $request, 'consReciMDFe');
        $this->lastRequest = $request;

        $parameters = ['mdfeDadosMsg' => $request];
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";
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
        if (empty($tpAmb)) {
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
                . "<chMDFe>$chave</chMDFe>"
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
     * @param  string $nProt
     * @param  string $xJust
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function sefazCancela($chave, $nProt, $xJust, $retornarXML = false) {

        $uf = $this->validKeyByUF($chave);
        $xJust = Strings::replaceSpecialsChars(
            substr(trim($xJust), 0, 255)
        );
        $tpEvento = 110111;
        $nSeqEvento = 1;
        $tagAdic = "<evCancMDFe><descEvento>Cancelamento</descEvento>"
                    . "<nProt>$nProt</nProt><xJust>$xJust</xJust>"
                 . "</evCancMDFe>";

        return $this->sefazEvento(
            $uf,
            $chave,
            $tpEvento,
            $nSeqEvento,
            $tagAdic,
            $retornarXML
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
        $nProt = '',
        $cUF = '',
        $cMun = '',
        $retornarXML = false
    ) {

        $siglaUF = $this->validKeyByUF($chave);

        if (empty($nProt)) {
            $msg = "Não foi passado o numero do protocolo!!";
            throw new InvalidArgumentException($msg);
        }

        //estabelece o codigo do tipo de evento ENCERRAMENTO
        $tpEvento = '110112';
        $nSeqEvento = '1';

        $dtEnc = date('Y-m-d');
        $tagAdic = "<evEncMDFe><descEvento>Encerramento</descEvento>"
                . "<nProt>$nProt</nProt><dtEnc>$dtEnc</dtEnc><cUF>$cUF</cUF>"
                . "<cMun>$cMun</cMun></evEncMDFe>";

        return $this->sefazEvento(
            $siglaUF,
            $chave,
            $tpEvento,
            $nSeqEvento,
            $tagAdic,
            $retornarXML
        );
    }

    /**
     * sefazIncluiCondutor
     *
     * @param  string $chave
     * @param  string $nSeqEvento
     * @param  string $xNome
     * @param  string $cpf
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function sefazIncluiCondutor(
        $chave = '',
        $nSeqEvento = '1',
        $xNome = '',
        $cpf = '',
        $retornarXML = false
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
            $nSeqEvento,
            $tagAdic,
            $retornarXML
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
        $body = "<mdfeDadosMsg xmlns=\"$this->urlNamespace\">$request</mdfeDadosMsg>";

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
        $tagAdic = '',
        $retornarXML
    ) {
        $servico = 'MDFeRecepcaoEvento';
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
        $request = "<infEvento Id=\"$eventId\">"
            . "<cOrgao>$cOrgao</cOrgao>"
            . "<tpAmb>$this->tpAmb</tpAmb>"
            . "<CNPJ>$cnpj</CNPJ>"
            . "<chMDFe>$chave</chMDFe>"
            . "<dhEvento>$dhEvento</dhEvento>"
            . "<tpEvento>$tpEvento</tpEvento>"
            . "<nSeqEvento>$nSeqEvento</nSeqEvento>"
            . "<detEvento versaoEvento=\"$this->urlVersion\">"
            . "$tagAdic"
            . "</detEvento>"
            . "</infEvento>";



        $lote = $dt->format('YmdHis').rand(0, 9);
        $request = "<eventoMDFe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
            . $request
            . "</eventoMDFe>";

        if ($retornarXML){
            return ['assinar' => $request];
        }

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
        $this->isValid($this->urlVersion, $request, 'eventoMDFe');

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
