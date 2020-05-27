<?php

namespace NFePHP\MDFe;

/**
 * Classe a construção do xml do Manifesto Eletrônico de Documentos Fiscais (MDF-e)
 * NOTA: Esta classe foi construida conforme estabelecido no
 * Manual de Orientação do Contribuinte
 * Padrões Técnicos de Comunicação do Manifesto Eletrônico de Documentos Fiscais
 * versão 1.00 de Junho de 2012
 *
 * @category  Library
 * @package   nfephp-org/sped-mdfe
 * @name      Make.php
 * @copyright 2009-2018 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-mdfe for the canonical source repository
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 */

use NFePHP\Common\Keys;
use NFePHP\Common\DOMImproved as Dom;
use DOMElement;
use stdClass;
use DateTime;

class Make
{
    /**
     * @var array
     */
    public $erros = [];

    /**
     * versao
     * numero da versão do xml da MDFe
     *
     * @var string
     */
    public $versao = '3.00';

    /**
     * mod
     * modelo da MDFe 58
     *
     * @var integer
     */
    public $mod = '58';

    /**
     * tpAmb
     * tipo de ambiente
     * @var string
     */
    public $tpAmb = '2';

    /**
     * chave da MDFe
     *
     * @var string
     */
    public $chMDFe = '';

    //propriedades privadas utilizadas internamente pela classe
    /**
     * @type string|\DOMNode
     */
    private $MDFe = '';

    /**
     * @var DOMElement
     */
    private $infMDFe;

    /**
     * @var DOMElement
     */
    protected $ide;

    /**
     * @var DOMElement
     */
    protected $emit;

    /**
     * @var DOMElement
     */
    protected $enderEmit;

    /**
     * @var DOMElement
     */
    private $infModal;

    /**
     * @var DOMElement
     */
    private $tot;

    /**
     * @var DOMElement
     */
    private $infAdic;

    /**
     * @var DOMElement
     */
    private $rodo;

    /**
     * @var DOMElement
     */
    private $veicTracao;

    /**
     * @var DOMElement
     */
    private $aereo;

    /**
     * @var DOMElement
     */
    private $trem;

    /**
     * @var DOMElement
     */
    private $aquav;

    /**
     * Informações do responsavel tecnico pela emissao do DF-e
     * @var \DOMNode
     */
    private $infRespTec = '';

    /**
     * @var DOMElement
     */
    protected $qrCodMDFe;

    /**
     * @var DOMElement
     */
    protected $infMDFeSupl;

    /**
     * @type string|\DOMNode
     */
    private $prodPred = null;

    private $aLacres = [];
    private $aInfCIOT = [];
    private $aInfContratante = [];
    private $aDisp = [];
    private $aVeicReboque = [];
    private $aPropVeicReboque = [];
    private $aLacRodo = [];
    private $aInfUnidTransp = [];
    private $aPeri = [];
    private $aInfUnidCarga = [];
    private $aSeg = [];
    private $aAutXML = [];
    private $aInfCTe = [];
    private $aInfNFe = [];
    private $aInfMDFe = [];

    /**
     * @type string|\DOMNode
     */
    private $infPag = [];

    /**
     * Função construtora cria um objeto DOMDocument
     * que será carregado com o documento fiscal
     */
    public function __construct()
    {
        $this->dom = new Dom('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;
    }

    /**
     * Returns xml string and assembly it is necessary
     * @return string
     */
    public function getXML()
    {
        if (empty($this->xml)) {
            $this->monta();
        }
        return $this->xml;
    }

    /**
     * Retorns the key number of NFe (44 digits)
     * @return string
     */
    public function getChave()
    {
        return $this->chMDFe;
    }

    /**
     * @param $indDoc
     * @return int|void
     */
    private function contaDoc($indDoc)
    {
        $total = 0;
        foreach ($indDoc as $doc) {
            $total += count($doc);
        }
        return $total;
    }

    /**
     * Mdfe xml mount method
     * @return boolean
     */
    public function monta()
    {
        if (count($this->erros) > 0) {
            return false;
        }
        //cria a tag raiz da MDFe
        $this->buildMDFe();
        //tag ide [4]
        $this->dom->appChild($this->infMDFe, $this->ide, 'Falta tag "infMDFe"');
        //tag emit [27]
        $this->dom->appChild($this->infMDFe, $this->emit, 'Falta tag "infMDFe"');
        if ($this->rodo) {
            $tpEmit = $this->ide->getElementsByTagName('tpEmit')->item(0)->nodeValue;
            if (($tpEmit == 1 || $tpEmit == 3) && empty($this->prodPred)) {
                $this->errors[] = "Tag prodPred é obrigatória para modal rodoviário!";
            }
            if (empty($this->infLotacao) and ($this->contaDoc($this->aInfCTe) + $this->contaDoc($this->aInfNFe) + $this->contaDoc($this->aInfMDFe)) == 1) {
                $this->errors[] = "Tag infLotacao é obrigatória quando só existir um Documento informado!";
            }
        }
        //tag infModal [43]
        $this->buildInfModal();
        $this->dom->appChild($this->infMDFe, $this->infModal, 'Falta tag "infMDFe"');
        //tag infDoc [46]
        $this->buildInfDoc();
        $this->dom->appChild($this->infMDFe, $this->infDoc, 'Falta tag "infMDFe"');
        //tag seg [118]

        if ($this->infPag) {
            $this->dom->addArrayChild($this->infANTT, $this->infPag, 'Falta tag "infpag"');
        }
        if (count($this->aSeg) > 0){
            $this->dom->addArrayChild($this->infMDFe, $this->aSeg, 'Falta tag "infMDFe"');
        }

        if (!empty($this->prodPred)) {
            $this->dom->appChild($this->infMDFe, $this->prodPred, 'Falta tag "prodPred"');
        }

        //tag tot [128]
        $this->dom->appChild($this->infMDFe, $this->tot, 'Falta tag "infMDFe"');
        //tag lacres [135]
        $this->dom->addArrayChild($this->infMDFe, $this->aLacres);
        //tag lacres [137]
        $this->dom->addArrayChild($this->infMDFe, $this->aAutXML);
        //tag lacres [140]
        $this->dom->appChild($this->infMDFe, $this->infAdic, 'Falta tag "infMDFe"');

        if ($this->infRespTec != '') {
            $this->dom->appChild($this->infMDFe, $this->infRespTec, 'Falta tag "infRespTec"');
        }

        //QrCode
        if ($this->qrCodMDFe){
            $this->dom->appChild($this->infMDFeSupl, $this->qrCodMDFe, 'Falta tag "qrCodMDFe');
        }

        //[1] tag infNFe [1]
        $this->dom->appChild($this->MDFe, $this->infMDFe, 'Falta tag "MDFe"');

        //infMDFeSupl
        if ($this->infMDFeSupl){
            $this->dom->appChild($this->MDFe, $this->infMDFeSupl,'Falta a tag "infMDFeSupl');
        }

        //[0] tag MDFe
        $this->dom->appendChild($this->MDFe);
        // testa da chave
        $this->checkMDFeKey($this->dom);
        $this->xml = $this->dom->saveXML();

        return true;
    }

    /**
     * taginfMDFe
     * Informações da MDFe 1 pai MDFe
     * tag MDFe/infMDFe
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function taginfMDFe(stdClass $std)
    {
        $this->infMDFe = $this->dom->createElement("infMDFe");
        $this->infMDFe->setAttribute("versao", $std->versao);
        $this->versao = $std->versao;
        return $this->infMDFe;
    }

    /**
     * tgaide
     * Informações de identificação da MDFe 4 pai 1
     * tag MDFe/infMDFe/ide
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagide(stdClass $std)
    {
        $this->tpAmb = $std->tpAmb;
        if ($std->dhEmi == '') {
            $std->dhEmi = DateTime::convertTimestampToSefazTime();
        }
        if (empty($std->cDV)) {
            $std->cDV = 0;
        }
        $identificador = '[4] <ide> - ';
        $ide = $this->dom->createElement("ide");
        $this->dom->addChild(
            $ide,
            "cUF",
            $std->cUF,
            true,
            $identificador . "Código da UF do emitente do Documento Fiscal"
        );
        $this->dom->addChild(
            $ide,
            "tpAmb",
            $std->tpAmb,
            true,
            $identificador . "Identificação do Ambiente"
        );
        $this->dom->addChild(
            $ide,
            "tpEmit",
            $std->tpEmit,
            true,
            $identificador . "Indicador da tipo de emitente"
        );

        if (isset($std->tpTransp)){
            $this->dom->addChild(
                $ide,
                "tpTransp",
                $std->tpTransp,
                true,
                $identificador . "Tipo do Transportador"
            );
        }

        $this->dom->addChild(
            $ide,
            "mod",
            $std->mod,
            true,
            $identificador . "Código do Modelo do Documento Fiscal"
        );
        $this->dom->addChild(
            $ide,
            "serie",
            $std->serie,
            true,
            $identificador . "Série do Documento Fiscal"
        );
        $this->dom->addChild(
            $ide,
            "nMDF",
            $std->nMDF,
            true,
            $identificador . "Número do Documento Fiscal"
        );
        $this->dom->addChild(
            $ide,
            "cMDF",
            $std->cMDF,
            true,
            $identificador . "Código do numérico do MDF"
        );
        $this->dom->addChild(
            $ide,
            "cDV",
            $std->cDV,
            true,
            $identificador . "Dígito Verificador da Chave de Acesso da NF-e"
        );
        $this->dom->addChild(
            $ide,
            "modal",
            $std->modal,
            true,
            $identificador . "Modalidade de transporte"
        );
        $this->dom->addChild(
            $ide,
            "dhEmi",
            $std->dhEmi,
            true,
            $identificador . "Data e hora de emissão do Documento Fiscal"
        );
        $this->dom->addChild(
            $ide,
            "tpEmis",
            $std->tpEmis,
            true,
            $identificador . "Tipo de Emissão do Documento Fiscal"
        );
        $this->dom->addChild(
            $ide,
            "procEmi",
            $std->procEmi,
            true,
            $identificador . "Processo de emissão"
        );
        $this->dom->addChild(
            $ide,
            "verProc",
            $std->verProc,
            true,
            $identificador . "Versão do Processo de emissão"
        );
        $this->dom->addChild(
            $ide,
            "UFIni",
            $std->ufIni,
            true,
            $identificador . "Sigla da UF do Carregamento"
        );
        $this->dom->addChild(
            $ide,
            "UFFim",
            $std->ufFim,
            true,
            $identificador . "Sigla da UF do Descarregamento"
        );
        $this->dom->addChild(
            $ide,
            "dhIniViagem",
            $std->dhIniViagem,
            true,
            $identificador . "Data e hora previstos de inicio da viagem"
        );
        $this->dom->addChild(
            $ide,
            "indCarregaPosterior",
            $std->indCarregaPosterior,
            false,
            $identificador . "Indicador de MDF-e com inclusão da Carga posterior a emissão por evento de inclusão de DF-e"
        );

        $this->mod = $std->mod;
        $this->ide = $ide;
        return $ide;
    }

    /**
     * tagInfMunCarrega
     *
     * tag MDFe/infMDFe/ide/infMunCarrega
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfMunCarrega(stdClass $std)
    {
        if(empty($this->ide)){
            $this->ide = $this->dom->createElement("ide");
        }
        $infMunCarrega = $this->dom->createElement("infMunCarrega");
        $this->dom->addChild(
            $infMunCarrega,
            "cMunCarrega",
            $std->cMunCarrega,
            true,
            "Código do Município de Carregamento"
        );
        $this->dom->addChild(
            $infMunCarrega,
            "xMunCarrega",
            $std->xMunCarrega,
            true,
            "Nome do Município de Carregamento"
        );
        if ($this->ide->getElementsByTagName("infMunCarrega")->length > 0) {
            $node = $this->ide->getElementsByTagName("infMunCarrega")->item(0);
        }else{
            $node = $this->ide->getElementsByTagName("dhIniViagem")->item(0);
        }
        $this->ide->insertBefore($infMunCarrega, $node);
        return $infMunCarrega;
    }

    /**
     * tagInfPercurso
     *
     * tag MDFe/infMDFe/ide/infPercurso
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfPercurso(stdClass $std)
    {
        if(empty($this->ide)){
            $this->ide = $this->dom->createElement("ide");
        }
        $infPercurso = $this->dom->createElement("infPercurso");
        $this->dom->addChild(
            $infPercurso,
            "UFPer",
            $std->ufPer,
            true,
            "Sigla das Unidades da Federação do percurso"
        );

        $this->ide->insertBefore($infPercurso, $this->ide->getElementsByTagName("dhIniViagem")->item(0));
        return $infPercurso;
    }

    /**
     * tagemit
     * Identificação do emitente da MDFe [25] pai 1
     * tag MDFe/infMDFe/emit
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagemit(stdClass $std)
    {
        $identificador = '[25] <emit> - ';
        $emit = $this->dom->createElement("emit");
        $this->dom->addChild(
            $emit,
            "CNPJ",
            $std->CNPJ,
            true,
            $identificador . "CNPJ do emitente"
        );
        $this->dom->addChild(
            $emit,
            "IE",
            $std->IE,
            true,
            $identificador . "Inscrição Estadual do emitente"
        );
        $this->dom->addChild(
            $emit,
            "xNome",
            $std->xNome,
            true,
            $identificador . "Razão Social ou Nome do emitente"
        );
        $this->dom->addChild(
            $emit,
            "xFant",
            $std->xFant,
            false,
            $identificador . "Nome fantasia do emitente"
        );
        $this->emit = $emit;
        return $emit;
    }

    /**
     * tagenderEmit
     * Endereço do emitente [30] pai [25]
     * tag MDFe/infMDFe/emit/endEmit
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagenderEmit(stdClass $std)
    {
        if(empty($this->emit)){
            $this->emit = $this->dom->createElement("emit");
        }
        $identificador = '[30] <enderEmit> - ';
        $enderEmit = $this->dom->createElement("enderEmit");
        $this->dom->addChild(
            $enderEmit,
            "xLgr",
            $std->xLgr,
            true,
            $identificador . "Logradouro do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "nro",
            $std->nro,
            true,
            $identificador . "Número do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "xCpl",
            $std->xCpl,
            false,
            $identificador . "Complemento do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "xBairro",
            $std->xBairro,
            true,
            $identificador . "Bairro do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "cMun",
            $std->cMun,
            true,
            $identificador . "Código do município do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "xMun",
            $std->xMun,
            true,
            $identificador . "Nome do município do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "CEP",
            $std->CEP,
            true,
            $identificador . "Código do CEP do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "UF",
            $std->UF,
            true,
            $identificador . "Sigla da UF do Endereço do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "fone",
            $std->fone,
            false,
            $identificador . "Número de telefone do emitente"
        );
        $this->dom->addChild(
            $enderEmit,
            "email",
            $std->email,
            false,
            $identificador . "Endereço de email do emitente"
        );

        $this->emit->appendChild($enderEmit);
        $this->enderEmit = $enderEmit;
        return $enderEmit;
    }

    /**
     * tagInfModal
     * tag MDFe/infMDFe/infModal
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfModal(stdClass $std)
    {
        $infModal = $this->dom->createElement("infModal");
        $infModal->setAttribute("versaoModal", $std->versaoModal);
        $this->infModal = $infModal;
        return $infModal;
    }

    /**
     * tagRodo
     * tag MDFe/infMDFe/infModal/rodo
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagRodo(stdClass $std)
    {
        $rodo = $this->dom->createElement("rodo");

        if (isset($std->codAgPorto) && $std->codAgPorto){
            $this->dom->addChild(
                $rodo,
                "codAgPorto",
                $std->codAgPorto,
                false,
                "Código de Agendamento no porto"
            );
        }
        $this->rodo = $rodo;
        return $rodo;
    }

    /**
     * tagInfANTT
     * tag MDFe/infMDFe/infModal/rodo/infANTT
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfANTT(stdClass $std)
    {
        if (empty($this->rodo)) {
            $this->rodo = $this->dom->createElement("rodo");
        }

        $this->infANTT = $this->dom->createElement("infANTT");

        $this->dom->addChild(
            $this->infANTT,
            "RNTRC",
            $std->RNTRC,
            false,
            "Registro Nacional de Transportadores Rodoviários de Carga"
        );

        $this->dom->addArrayChild($this->infANTT, $this->aInfCIOT);

         if (!empty($this->aDisp)){
            $valePed = $this->dom->createElement("valePed");

            foreach ($this->aDisp as $node) {
                $this->dom->appChild($valePed, $node, '');
            }

            $this->dom->appChild($this->infANTT, $valePed, '');
        }

        $this->dom->addArrayChild($this->infANTT, $this->aInfContratante);

        $this->rodo->insertBefore($this->infANTT);
        return $this->infANTT;
    }

    /**
     * tagInfCIOT
     * tag MDFe/infMDFe/infModal/rodo/infANTT/infCIOT
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfCIOT(stdClass $std)
    {
        $infCIOT = $this->dom->createElement("infCIOT");
        $this->dom->addChild(
            $infCIOT,
            "CIOT",
            $std->CIOT,
            false,
            "Código Identificador da Operação de Transporte"
        );

        if (!empty($std->CPF)){
            $this->dom->addChild(
                $infCIOT,
                "CPF",
                $std->CPF,
                false,
                "Número do CPF responsável pela geração do CIOT"
            );
        }
        else
        if (!empty($std->CNPJ)){
            $this->dom->addChild(
                $infCIOT,
                "CNPJ",
                $std->CNPJ,
                false,
                "Número do CNPJ responsável pela geração do CIOT"
            );
        }

        $this->aInfCIOT[] = $infCIOT;
        return $infCIOT;
    }

    /**
     * tagDisp
     * tag MDFe/infMDFe/infModal/rodo/infANTT/disp
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagDisp(stdClass $std)
    {
        $disp = $this->dom->createElement("disp");
        $this->dom->addChild(
            $disp,
            "CNPJForn",
            $std->CNPJForn,
            false,
            "CNPJ da empresa fornecedora do Vale-Pedágio"
        );

        if (isset($std->CNPJPg)){
            $this->dom->addChild(
                $disp,
                "CNPJPg",
                $std->CNPJPg,
                false,
                "CNPJ do responsável pelo pagamento do Vale-Pedágio"
            );
        }
        else
        if (isset($std->CPFPg)){
            $this->dom->addChild(
                $disp,
                "CPFPg",
                $std->CPFPg,
                false,
                "CNPJ do responsável pelo pagamento do Vale-Pedágio"
            );
        }

        $this->dom->addChild(
            $disp,
            "nCompra",
            $std->nCompra,
            false,
            "Número do comprovante de compra"
        );
        $this->dom->addChild(
            $disp,
            "vValePed",
            $std->vValePed,
            false,
            "Valor do Vale-Pedagio"
        );

        $this->aDisp[] = $disp;
        return $disp;
    }

    /**
     * tagInfContratante
     * tag MDFe/infMDFe/infModal/rodo/infANTT/infContratante
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfContratante(stdClass $std)
    {
        $infContratante = $this->dom->createElement("infContratante");
        $this->dom->addChild(
            $infContratante,
            "CPF",
            $std->CPF,
            false,
            "Número do CPF do contratente do serviço"
        );
        $this->dom->addChild(
            $infContratante,
            "CNPJ",
            $std->CNPJ,
            false,
            "Número do CNPJ do contratante do serviço"
        );
        $this->aInfContratante[] = $infContratante;
        return $infContratante;
    }

    /**
     * tagVeicTracao
     * tag MDFe/infMDFe/infModal/rodo/veicTracao
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagVeicTracao(stdClass $std)
    {
        $veicTracao = $this->dom->createElement('veicTracao');
        $this->dom->addChild(
            $veicTracao,
            "cInt",
            $std->cInt,
            true,
            "Código interno do veículo"
        );
        $this->dom->addChild(
            $veicTracao,
            "placa",
            $std->placa,
            true,
            "Placa do veículo"
        );
        if(isset($std->RENAVAM)){
            $this->dom->addChild(
                $veicTracao,
                "RENAVAM",
                $std->RENAVAM,
                true,
                "RENAVAM do veículo"
            );
        }
        $this->dom->addChild(
            $veicTracao,
            "tara",
            $std->tara,
            true,
            "Tara em KG"
        );
        if(isset($std->capKG)){
            $this->dom->addChild(
                $veicTracao,
                "capKG",
                $std->capKG,
                true,
                "Capacidade em KG"
            );
        }
        if(isset($std->capM3)){
            $this->dom->addChild(
                $veicTracao,
                "capM3",
                $std->capM3,
                true,
                "Capacidade em M3"
            );
        }
        $this->dom->addChild(
            $veicTracao,
            "tpRod",
            $std->tpRod,
            true,
            "Tipo de Rodado"
        );
        $this->dom->addChild(
            $veicTracao,
            "tpCar",
            $std->tpCar,
            true,
            "Tipo de Carroceria"
        );
        $this->dom->addChild(
            $veicTracao,
            "UF",
            $std->UF,
            true,
            "UF em que veículo está licenciado"
        );
        $this->veicTracao = $veicTracao;
        return $veicTracao;
    }

    /**
     * tagProp
     * tag MDFe/infMDFe/infModal/rodo/veicTracao/prop
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagPropVeicTracao(stdClass $std)
    {
        if (empty($this->veicTracao)) {
            $this->veicTracao = $this->dom->createElement('veicTracao');
        }
        $this->propVeicTracao = $this->dom->createElement('prop');

        if (isset($std->CPF)){
            $this->dom->addChild(
                $this->propVeicTracao,
                "CPF",
                $std->CPF,
                true,
                "Número do CPF"
            );
        }
        else
        if (isset($std->CNPJ)){
            $this->dom->addChild(
                $this->propVeicTracao,
                "CNPJ",
                $std->CNPJ,
                true,
                "Número do CNPJ"
            );
        }

        $this->dom->addChild(
            $this->propVeicTracao,
            "RNTRC",
            $std->RNTRC,
            true,
            "Registro Nacional dos Transportadores Rodoviários de Carga"
        );
        $this->dom->addChild(
            $this->propVeicTracao,
            "xNome",
            $std->xNome,
            true,
            "Razão Social ou Nome do proprietário"
        );

        $this->dom->addChild(
            $this->propVeicTracao,
            "IE",
            $std->IE,
            true,
            "Inscrição Estadual"
        );

        if (isset($std->UF)){
            $this->dom->addChild(
                $this->propVeicTracao,
                "UF",
                $std->UF,
                true,
                "UF"
            );
        }

        $this->dom->addChild(
            $this->propVeicTracao,
            "tpProp",
            $std->tpProp,
            true,
            "Tipo Proprietário"
        );

        if ($this->veicTracao->getElementsByTagName("condutor")->length > 0) {
            $node = $this->veicTracao->getElementsByTagName("condutor")->item(0);
        }else{
            $node = $this->veicTracao->getElementsByTagName("tpRod")->item(0);
        }
        $this->veicTracao->insertBefore($this->propVeicTracao, $node);
        return $this->propVeicTracao;
    }

    /**
     * tagCondutor
     * tag MDFe/infMDFe/infModal/rodo/veicTracao/condutor
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagCondutor(stdClass $std)
    {
        $condutor = $this->dom->createElement("condutor");
        $this->dom->addChild(
            $condutor,
            "xNome",
            $std->xNome,
            true,
            "Nome do condutor"
        );
        $this->dom->addChild(
            $condutor,
            "CPF",
            $std->cpf,
            true,
            "CPF do condutor"
        );
        $this->aCondutor[] = $condutor;
        return $condutor;
    }

    /**
     * tagVeicReboque
     * tag MDFe/infMDFe/infModal/rodo/reboque
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagVeicReboque(stdClass $std)
    {
        $reboque = $this->dom->createElement("veicReboque");
        $this->dom->addChild(
            $reboque,
            "cInt",
            $std->cInt,
            true,
            "Código interno do veículo"
        );
        $this->dom->addChild(
            $reboque,
            "placa",
            $std->placa,
            true,
            "Placa do veículo"
        );
        if(isset($std->RENAVAM)){
            $this->dom->addChild(
                $reboque,
                "RENAVAM",
                $std->RENAVAM,
                true,
                "RENAVAM do veículo"
            );
        }
        $this->dom->addChild(
            $reboque,
            "tara",
            $std->tara,
            true,
            "Tara em KG"
        );
        if(isset($std->capKG)){
            $this->dom->addChild(
                $reboque,
                "capKG",
                $std->capKG,
                true,
                "Capacidade em KG"
            );
        }
        if(isset($std->capM3)){
            $this->dom->addChild(
                $reboque,
                "capM3",
                $std->capM3,
                true,
                "Capacidade em M3"
            );
        }
        $this->dom->addChild(
            $reboque,
            "tpCar",
            $std->tpCar,
            true,
            "Tipo de Carroceria"
        );
        $this->dom->addChild(
            $reboque,
            "UF",
            $std->UF,
            true,
            "UF em que veículo está licenciado"
        );
        $this->aVeicReboque[$std->nItem] = $reboque;
        return $reboque;
    }

    /**
     * tagProp
     * tag MDFe/infMDFe/infModal/rodo/veicReboque/prop
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagPropVeicReboque(stdClass $std)
    {
        $propVeicReboque = $this->dom->createElement('prop');

        if (isset($std->CPF)){
            $this->dom->addChild(
                $propVeicReboque,
                "CPF",
                $std->CPF,
                true,
                "Número do CPF"
            );
        }
        else
        if (isset($std->CNPJ)){
            $this->dom->addChild(
                $propVeicReboque,
                "CNPJ",
                $std->CNPJ,
                true,
                "Número do CNPJ"
            );
        }

        $this->dom->addChild(
            $propVeicReboque,
            "RNTRC",
            $std->RNTRC,
            true,
            "Registro Nacional dos Transportadores Rodoviários de Carga"
        );
        $this->dom->addChild(
            $propVeicReboque,
            "xNome",
            $std->xNome,
            true,
            "Razão Social ou Nome do proprietário"
        );

        $this->dom->addChild(
            $propVeicReboque,
            "IE",
            $std->IE,
            true,
            "Inscrição Estadual"
        );

        if (isset($std->UF)){
            $this->dom->addChild(
                $propVeicReboque,
                "UF",
                $std->UF,
                true,
                "UF"
            );
        }

        $this->dom->addChild(
            $propVeicReboque,
            "tpProp",
            $std->tpProp,
            true,
            "Tipo Proprietário"
        );
        $this->aPropVeicReboque[$std->nItem] = $propVeicReboque;
        return $propVeicReboque;
    }

    /**
     * tagLacRodo
     * tag MDFe/infMDFe/infModal/rodo/lacRodo
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagLacRodo(stdClass $std)
    {
        $lacre = $this->dom->createElement("lacRodo");
        $this->dom->addChild(
            $lacre,
            "nLacre",
            $std->nLacre,
            true,
            "Código interno do veículo"
        );
        $this->aLacRodo[] = $lacre;
        return $lacre;
    }

    /**
     * tagAereo
     * tag MDFe/infMDFe/infModal/aereo
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagAereo(stdClass $std)
    {
        $aereo = $this->dom->createElement("aereo");
        $this->dom->addChild(
            $aereo,
            "nac",
            $std->nac,
            true,
            "Marca da Nacionalidade da aeronave"
        );
        $this->dom->addChild(
            $aereo,
            "matr",
            $std->matr,
            true,
            "Marca de Matrícula da aeronave"
        );
        $this->dom->addChild(
            $aereo,
            "nVoo",
            $std->nVoo,
            true,
            "Número do Vôo"
        );
        $this->dom->addChild(
            $aereo,
            "cAerEmb",
            $std->cAerEmb,
            true,
            "Aeródromo de Embarque - Código IATA"
        );
        $this->dom->addChild(
            $aereo,
            "cAerDes",
            $std->cAerDes,
            true,
            "Aeródromo de Destino - Código IATA"
        );
        $this->dom->addChild(
            $aereo,
            "dVoo",
            $std->dVoo,
            true,
            "Data do Vôo"
        );
        $this->aereo = $aereo;
        return $aereo;
    }

    /**
     * tagAquav
     * tag MDFe/infMDFe/infModal/aquav
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagAquav(stdClass $std)
    {
        $aquav = $this->dom->createElement("aquav");
        $this->dom->addChild(
            $aquav,
            "irin",
            $std->irin,
            true,
            "Irin do navio sempre deverá ser informado"
        );
        $this->dom->addChild(
            $aquav,
            "tpEmb",
            $std->tpEmb,
            true,
            "Código do tipo de embarcação"
        );
        $this->dom->addChild(
            $aquav,
            "cEmbar",
            $std->cEmbar,
            true,
            "Código da Embarcação"
        );
        $this->dom->addChild(
            $aquav,
            "xEmbar",
            $std->xEmbar,
            true,
            "Nome da embarcação"
        );
        $this->dom->addChild(
            $aquav,
            "nViag",
            $std->nViag,
            true,
            "Número da Viagem"
        );
        $this->dom->addChild(
            $aquav,
            "cPrtEmb",
            $std->cPrtEmb,
            true,
            "Código do Porto de Embarque"
        );
        $this->dom->addChild(
            $aquav,
            "cPrtDest",
            $std->cPrtDest,
            true,
            "Código do Porto de Destino"
        );
        $this->dom->addChild(
            $aquav,
            "prtTrans",
            $std->prtTrans,
            true,
            "Porto de Transbordo"
        );
        $this->dom->addChild(
            $aquav,
            "tpNav",
            $std->tpNav,
            true,
            "Tipo de Navegação"
        );
        $this->aquav = $aquav;
        return $aquav;
    }

    /**
     * tagInfTermCarreg
     * tag MDFe/infMDFe/infModal/aquav/infTermCarreg
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfTermCarreg(stdClass $std)
    {
        $infTermCarreg = $this->dom->createElement("infTermCarreg");
        $this->dom->addChild(
            $infTermCarreg,
            "cTermCarreg",
            $std->cTermCarreg,
            true,
            "Código do Terminal de Carregamento"
        );
        $this->dom->addChild(
            $infTermCarreg,
            "xTermCarreg",
            $std->xTermCarreg,
            true,
            "Nome do Terminal de Carregamento"
        );
        $this->aInfTermCarreg[] = $infTermCarreg;
        return $infTermCarreg;
    }

    /**
     * tagInfTermDescarreg
     * tag MDFe/infMDFe/infModal/aquav/infTermDescarreg
     *
     * @param  string $cTermDescarreg
     *
     * @return DOMElement
     */
    public function tagInfTermDescarreg(stdClass $std)
    {
        $infTermDescarreg = $this->dom->createElement("infTermDescarreg");
        $this->dom->addChild(
            $infTermDescarreg,
            "cTermDescarreg",
            $std->cTermDescarreg,
            true,
            "Código do Terminal de Descarregamento"
        );
        $this->dom->addChild(
            $infTermDescarreg,
            "xTermDescarreg",
            $std->xTermDescarreg,
            true,
            "Nome do Terminal de Descarregamento"
        );
        $this->aInfTermDescarreg[] = $infTermDescarreg;
        return $infTermDescarreg;
    }

    /**
     * tagInfEmbComb
     * tag MDFe/infMDFe/infModal/aquav/infEmbComb
     *
     * @param  string $cTermDescarreg
     *
     * @return DOMElement
     */
    public function tagInfEmbComb(stdClass $std)
    {
        $infEmbComb = $this->dom->createElement("infEmbComb");
        $this->dom->addChild(
            $infEmbComb,
            "cEmbComb",
            $std->cEmbComb,
            true,
            "Código da embarcação do comboio"
        );
        $this->dom->addChild(
            $infEmbComb,
            "xBalsa",
            $std->xBalsa,
            true,
            "Identificador da Balsa"
        );
        $this->aInfEmbComb[] = $infEmbComb;
        return $infEmbComb;
    }

    /**
     * tagInfUnidCargaVazia
     * tag MDFe/infMDFe/infModal/aquav/infUnidCargaVazia
     *
     * @param  string $cTermDescarreg
     *
     * @return DOMElement
     */
    public function tagInfUnidCargaVazia(stdClass $std)
    {
        $infUnidCargaVazia = $this->dom->createElement("infUnidCargaVazia");
        $this->dom->addChild(
            $infUnidCargaVazia,
            "idUnidCargaVazia",
            $std->idUnidCargaVazia,
            true,
            "Identificação da unidades de carga vazia"
        );
        $this->dom->addChild(
            $infUnidCargaVazia,
            "tpUnidCargaVazia",
            $std->tpUnidCargaVazia,
            true,
            "Tipo da unidade de carga vazia"
        );
        $this->aInfUnidCargaVazia[] = $infUnidCargaVazia;
        return $infUnidCargaVazia;
    }

    /**
     * tagInfUnidTranspVazia
     * tag MDFe/infMDFe/infModal/aquav/infUnidTranspVazia
     *
     * @param  string $cTermDescarreg
     *
     * @return DOMElement
     */
    public function tagInfUnidTranspVazia(stdClass $std)
    {
        $infUnidTranspVazia = $this->dom->createElement("infUnidTranspVazia");
        $this->dom->addChild(
            $infUnidTranspVazia,
            "idUnidTranspVazia",
            $std->idUnidTranspVazia,
            true,
            "Identificação da unidades de transporte vazia"
        );
        $this->dom->addChild(
            $infUnidTranspVazia,
            "tpUnidTranspVazia",
            $std->tpUnidTranspVazia,
            true,
            "Tipo da unidade de transporte vazia"
        );
        $this->aInfUnidTranspVazia[] = $infUnidTranspVazia;
        return $infUnidTranspVazia;
    }

    /**
     * tagTrem
     * tag MDFe/infMDFe/infModal/ferrov/trem
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagTrem(stdClass $std)
    {
        $trem = $this->dom->createElement("trem");
        $this->dom->addChild(
            $trem,
            "xPref",
            $std->xPref,
            true,
            "Prefixo do Trem"
        );
        $this->dom->addChild(
            $trem,
            "dhTrem",
            $std->dhTrem,
            false,
            "Data e hora de liberação do trem na origem"
        );
        $this->dom->addChild(
            $trem,
            "xOri",
            $std->xOri,
            true,
            "Origem do Trem"
        );
        $this->dom->addChild(
            $trem,
            "xDest",
            $std->xDest,
            true,
            "Destino do Trem"
        );
        $this->dom->addChild(
            $trem,
            "qVag",
            $std->qVag,
            true,
            "Quantidade de vagões"
        );
        $this->trem = $trem;
        return $trem;
    }

    /**
     * tagVag
     * tag MDFe/infMDFe/infModal/ferrov/trem/vag
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagVag(stdClass $std)
    {
        $vag = $this->dom->createElement("vag");
        $this->dom->addChild(
            $vag,
            "pesoBC",
            $std->pesoBC,
            true,
            "Peso Base de Cálculo de Frete em Toneladas"
        );
        $this->dom->addChild(
            $vag,
            "pesoR",
            $std->pesoR,
            true,
            "Peso Real em Toneladas"
        );
        $this->dom->addChild(
            $vag,
            "tpVag",
            $std->tpVag,
            true,
            "Tipo de Vagão"
        );
        $this->dom->addChild(
            $vag,
            "serie",
            $std->serie,
            true,
            "Série de Identificação do vagão"
        );
        $this->dom->addChild(
            $vag,
            "nVag",
            $std->nVag,
            true,
            "Número de Identificação do vagão"
        );
        $this->dom->addChild(
            $vag,
            "nSeq",
            $std->nSeq,
            false,
            "Sequência do vagão na composição"
        );
        $this->dom->addChild(
            $vag,
            "TU",
            $std->TU,
            true,
            "Tonelada Útil"
        );
        $this->aVag[] = $vag;
        return $vag;
    }

    /**
     * tagInfMunDescarga
     * tag MDFe/infMDFe/infDoc/infMunDescarga
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfMunDescarga(stdClass $std)
    {
        $infMunDescarga = $this->dom->createElement("infMunDescarga");
        $this->dom->addChild(
            $infMunDescarga,
            "cMunDescarga",
            $std->cMunDescarga,
            true,
            "Código do Município de Descarga"
        );
        $this->dom->addChild(
            $infMunDescarga,
            "xMunDescarga",
            $std->xMunDescarga,
            true,
            "Nome do Município de Descarga"
        );
        $this->aInfMunDescarga[$std->nItem] = $infMunDescarga;
        return $infMunDescarga;
    }

    /**
     * tagInfCTe
     * tag MDFe/infMDFe/infDoc/infMunDescarga/infCTe
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfCTe(stdClass $std)
    {
        $infCTe = $this->dom->createElement("infCTe");
        $this->dom->addChild(
            $infCTe,
            "chCTe",
            $std->chCTe,
            true,
            "Chave de Acesso CTe"
        );
        $this->dom->addChild(
            $infCTe,
            "SegCodBarra",
            $std->segCodBarra,
            false,
            "Segundo código de barras do CTe"
        );

        if (isset($std->indReentrega)){
            $this->dom->addChild(
                $infCTe,
                "indReentrega",
                $std->indReentrega,
                false,
                "Indicador de Reentrega do CTe"
            );
        }

        if (isset($this->aInfUnidTransp[$std->nItemFilho]) && count($this->aInfUnidTransp[$std->nItemFilho]) > 0){
            $this->dom->addArrayChild($infCTe, $this->aInfUnidTransp[$std->nItemFilho]);
        }

        if (isset($this->aPeri[$std->nItemFilho]) && count($this->aPeri[$std->nItemFilho]) > 0){
            $this->dom->addArrayChild($infCTe, $this->aPeri[$std->nItemFilho]);
        }

        $this->aInfCTe[$std->nItem][] = $infCTe;
        return $infCTe;
    }

    public function tagPeri(stdClass $std){
        $peri = $this->dom->createElement("peri");
        $this->dom->addChild(
            $peri,
            "nONU",
            $std->nONU,
            true,
            "Número ONU/UN"
        );

        if (isset($std->xNomeAE) && $std->xNomeAE){
            $this->dom->addChild(
                $peri,
                "xNomeAE",
                $std->xNomeAE,
                false,
                "Nome apropriado para embarque do produto"
            );
        }

        if (isset($std->xClaRisco) && $std->xClaRisco){
            $this->dom->addChild(
                $peri,
                "xClaRisco",
                $std->xClaRisco,
                false,
                "Classe ou subclasse/divisão, e risco subsidiário/risco secundário"
            );
        }

        if (isset($std->grEmb) && $std->grEmb){
            $this->dom->addChild(
                $peri,
                "grEmb",
                $std->grEmb,
                false,
                "Grupo de Embalagem"
            );
        }

        $this->dom->addChild(
            $peri,
            "qTotProd",
            $std->qTotProd,
            true,
            "Quantidade total por produto"
        );

        if (isset($std->qVolTipo) && $std->qVolTipo){
            $this->dom->addChild(
                $peri,
                "qVolTipo",
                $std->qVolTipo,
                false,
                "Quantidade e Tipo de volumes"
            );
        }

        $this->aPeri[$std->nItem][] = $peri;
        return $peri;
    }

    public function tagInfUnidTransp(stdClass $std){
        $infUnidTransp = $this->dom->createElement("infUnidTransp");
        $this->dom->addChild(
            $infUnidTransp,
            "tpUnidTransp",
            $std->tpUnidTransp,
            true,
            "Tipo da Unidade de Transporte"
        );
        $this->dom->addChild(
            $infUnidTransp,
            "idUnidTransp",
            $std->idUnidTransp,
            false,
            "Identificação da Unidade de Transporte"
        );

        if (isset($std->lacres) && count($std->lacres) > 0){
            foreach ($std->lacres as $lacre) {
                $lacres = $this->dom->createElement("lacUnidTransp");

                $this->dom->addChild(
                    $lacres,
                    "nLacre",
                    $lacre,
                    true,
                    "Número do lacre"
                );

                $this->dom->appChild(
                    $infUnidTransp,
                    $lacres
                );
            }
        }

        if (isset($this->aInfUnidCarga[$std->nItem]) && count($this->aInfUnidCarga[$std->nItem]) > 0){
            $this->dom->addArrayChild($infUnidTransp, $this->aInfUnidCarga[$std->nItem]);
        }

        if (isset($std->qtdRat) && $std->qtdRat){
            $this->dom->addChild(
                $infUnidTransp,
                "qtdRat",
                $std->qtdRat,
                false,
                "Quantidade rateada (Peso,Volume)"
            );
        }

        $this->aInfUnidTransp[$std->nItem][] = $infUnidTransp;
        return $infUnidTransp;
    }

    public function tagInfUnidCarga(stdClass $std){
        $infUnidCarga = $this->dom->createElement("infUnidCarga");
        $this->dom->addChild(
            $infUnidCarga,
            "tpUnidCarga",
            $std->tpUnidCarga,
            true,
            "Tipo da Unidade de Carga"
        );
        $this->dom->addChild(
            $infUnidCarga,
            "idUnidCarga",
            $std->idUnidCarga,
            false,
            "Identificação da Unidade de Carga"
        );

        if (isset($std->lacres) && count($std->lacres) > 0){
            foreach ($std->lacres as $lacre) {
                $lacres = $this->dom->createElement("lacUnidCarga");

                $this->dom->addChild(
                    $lacres,
                    "nLacre",
                    $lacre,
                    true,
                    "Número do lacre"
                );

                $this->dom->appChild(
                    $infUnidCarga,
                    $lacres
                );
            }
        }

        if (isset($std->qtdRat) && $std->qtdRat){
            $this->dom->addChild(
                $infUnidCarga,
                "qtdRat",
                $std->qtdRat,
                false,
                "Quantidade rateada (Peso,Volume)"
            );
        }

        $this->aInfUnidCarga[$std->nItem][] = $infUnidCarga;
        return $infUnidCarga;
    }

    /**
     * tagInfNFe
     * tag MDFe/infMDFe/infDoc/infMunDescarga/infNFe
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfNFe(stdClass $std)
    {
        $infNFe = $this->dom->createElement("infNFe");
        $this->dom->addChild(
            $infNFe,
            "chNFe",
            $std->chNFe,
            true,
            "Chave de Acesso da NFe"
        );
        $this->dom->addChild(
            $infNFe,
            "SegCodBarra",
            $std->segCodBarra,
            false,
            "Segundo código de barras da NFe"
        );

        if (isset($std->indReentrega)){
            $this->dom->addChild(
                $infNFe,
                "indReentrega",
                $std->indReentrega,
                false,
                "Indicador de Reentrega da NFe"
            );
        }

        if (isset($this->aInfUnidTransp[$std->nItemFilho]) && count($this->aInfUnidTransp[$std->nItemFilho]) > 0){
            $this->dom->addArrayChild($infNFe, $this->aInfUnidTransp[$std->nItemFilho]);
        }

        $this->aInfNFe[$std->nItem][] = $infNFe;
        return $infNFe;
    }

    /**
     * tagInfMDFeTransp
     * tag MDFe/infMDFeTransp/infDoc/infMunDescarga/infMDFeTranspTransp
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagInfMDFeTransp(stdClass $std)
    {
        $infMDFeTransp = $this->dom->createElement("infMDFeTransp");
        $this->dom->addChild(
            $infMDFeTransp,
            "chMDFe",
            $std->chMDFe,
            true,
            "Chave de Acesso da MDFe"
        );
        $this->aInfMDFe[$std->nItem][] = $infMDFeTransp;
        return $infMDFeTransp;
    }

    /**
     * tagSeg
     * tag MDFe/infMDFe/seg
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagSeg(stdClass $std)
    {
        $seg = $this->dom->createElement("seg");

        $infResp = $this->dom->createElement("infResp");
        $this->dom->addChild(
            $infResp,
            "respSeg",
            $std->respSeg->resp,
            false,
            "Responsável pelo seguro"
        );

        if (isset($std->respSeg->CNPJ) && $std->respSeg->CNPJ){
            $this->dom->addChild(
                $infResp,
                "CNPJ",
                $std->respSeg->CNPJ,
                false,
                "Número do CNPJ do responsável pelo seguro"
            );
        }

        if (isset($std->respSeg->CPF) && $std->respSeg->CPF){
            $this->dom->addChild(
                $infResp,
                "CPF",
                $std->respSeg->CPF,
                false,
                "Número do CPF do responsável pelo seguro"
            );
        }

        $this->dom->appChild($seg, $infResp);

        $infSeg = $this->dom->createElement("infSeg");
        $this->dom->addChild(
            $infSeg,
            "xSeg",
            $std->xSeg,
            false,
            "Nome da Seguradora"
        );
        $this->dom->addChild(
            $infSeg,
            "CNPJ",
            $std->CNPJ,
            false,
            "Número do CNPJ da seguradora"
        );
        $this->dom->appChild($seg, $infSeg);

        $this->dom->addChild(
            $seg,
            "nApol",
            $std->nApol,
            false,
            "Número da Apólice"
        );

        if (isset($std->aNAver)){
            foreach ($std->aNAver as $nAver) {
                $this->dom->addChild(
                    $seg,
                    "nAver",
                    $nAver,
                    false,
                    "Número da Averbação"
                );
            }
        }

        $this->aSeg[] = $seg;
        return $seg;
    }

        /**
     * tagprodPred
     * tag MDFe/infMDFe/prodPred
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagprodPred($std)
    {
        $this->prodPred = $this->dom->createElement("prodPred");
        $this->dom->addChild(
            $this->prodPred,
            "tpCarga",
            $std->tpCarga,
            true,
            "Tipo da Carga. 01-Granel sólido; 02-Granel líquido; 03-Frigorificada; 04-Conteinerizada; 05-Carga Geral; 06-Neogranel; 07-Perigosa (granel sólido); 08-Perigosa (granel líquido); 09-Perigosa (carga frigorificada); 10-Perigosa (conteinerizada); 11-Perigosa (carga geral)."
        );
        $this->dom->addChild(
            $this->prodPred,
            "xProd",
            $std->xProd,
            true,
            "Descrição do produto predominante"
        );
        $this->dom->addChild(
            $this->prodPred,
            "cEAN",
            $std->cEAN,
            false,
            "GTIN (Global Trade Item Number) do produto, antigo código EAN ou código de barras"
        );
        $this->dom->addChild(
            $this->prodPred,
            "NCM",
            $std->NCM,
            false,
            "Código NCM"
        );
        if (!empty($std->infLotacao)) {
            $this->dom->appChild($this->prodPred, $this->taginfLotacao($std->infLotacao), 'Falta tag "infLotacao"');
        }
        return $this->prodPred;
    }

    /**
     *
     */
    private function taginfLotacao(stdClass $std)
    {
        $this->infLotacao = $this->dom->createElement("infLotacao");
        if (!empty($std->infLocalCarrega)) {
            $this->dom->appChild($this->infLotacao, $this->tagLocalCarrega($std->infLocalCarrega), 'Falta tag "infLocalCarrega"');
        }
        if (!empty($std->infLocalDescarrega)) {
            $this->dom->appChild($this->infLotacao, $this->tagLocalDescarrega($std->infLocalDescarrega), 'Falta tag "infLocalDescarrega"');
        }
        return $this->infLotacao;
    }

      /**
     * Informações da localização do carregamento do MDF-e de carga lotação
     *
     */
    private function tagLocalCarrega(stdClass $std)
    {
        $tagLocalCarrega = $this->dom->createElement("infLocalCarrega");
        if (!empty($std->CEP)) {
            $this->dom->addChild(
                $tagLocalCarrega,
                "CEP",
                $std->CEP,
                true,
                "CEP onde foi carregado o MDF-e"
            );
        } else {
            $this->dom->addChild(
                $tagLocalCarrega,
                "latitude",
                $std->latitude,
                true,
                "Latitude do ponto geográfico onde foi carregado o MDF-e"
            );
            $this->dom->addChild(
                $tagLocalCarrega,
                "longitude",
                $std->longitude,
                true,
                "Longitude do ponto geográfico onde foi carregado o MDF-e"
            );
        }

        return $tagLocalCarrega;
    }

    /**
     * Informações da localização do descarregamento do MDF-e de carga lotação
     */
    private function tagLocalDescarrega(stdClass $std)
    {
        $tagLocalDescarrega = $this->dom->createElement("infLocalDescarrega");
        if (!empty($std->CEP)) {
            $this->dom->addChild(
                $tagLocalDescarrega,
                "CEP",
                $std->CEP,
                true,
                "CEP onde foi descarregado o MDF-e"
            );
        } else {
            $this->dom->addChild(
                $tagLocalDescarrega,
                "latitude",
                $std->latitude,
                true,
                "Latitude do ponto geográfico onde foi descarregado o MDF-e"
            );
            $this->dom->addChild(
                $tagLocalDescarrega,
                "longitude",
                $std->longitude,
                true,
                "Longitude do ponto geográfico onde foi descarregado o MDF-e"
            );
        }
        return $tagLocalDescarrega;
    }

    /**
     * tagTot
     * tag MDFe/infMDFe/tot
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagTot(stdClass $std)
    {
        $tot = $this->dom->createElement("tot");
        $this->dom->addChild(
            $tot,
            "qCTe",
            $std->qCTe,
            false,
            "Quantidade total de CT-e relacionados no Manifesto"
        );
        $this->dom->addChild(
            $tot,
            "qNFe",
            $std->qNFe,
            false,
            "Quantidade total de NF-e relacionados no Manifesto"
        );

        if (isset($std->qMDFe)){
            $this->dom->addChild(
                $tot,
                "qMDFe",
                $std->qMDFe,
                false,
                "Quantidade total de MDF-e relacionados no Manifesto"
            );
        }

        $this->dom->addChild(
            $tot,
            "vCarga",
            $std->vCarga,
            true,
            "Valor total da mercadoria/carga transportada"
        );
        $this->dom->addChild(
            $tot,
            "cUnid",
            $std->cUnid,
            true,
            "Código da unidade de medida do Peso Bruto da Carga / Mercadoria Transportada"
        );
        $this->dom->addChild(
            $tot,
            "qCarga",
            $std->qCarga,
            true,
            "Peso Bruto Total da Carga / Mercadoria Transportada"
        );
        $this->tot = $tot;
        return $tot;
    }

    /**
     * tagLacres
     * tag MDFe/infMDFe/lacres
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagLacres(stdClass $std)
    {
        $lacres = $this->dom->createElement("lacres");
        $this->dom->addChild(
            $lacres,
            "nLacre",
            $std->nLacre,
            false,
            "Número do lacre"
        );
        $this->aLacres[] = $lacres;
        return $lacres;
    }

    /**
     * tagAutXML
     * tag MDFe/infMDFe/autXML
     *
     * Autorizados para download do XML do MDF-e
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function tagautXML($std)
    {
        $autXML = $this->dom->createElement('autXML');
        if (isset($std->CNPJ) && $std->CNPJ != '') {
            $this->dom->addChild(
                $autXML,
                'CNPJ',
                $std->CNPJ,
                true,
                'CNPJ do Cliente Autorizado'
            );
        } elseif (isset($std->CPF) && $std->CPF != '') {
            $this->dom->addChild(
                $autXML,
                'CPF',
                $std->CPF,
                true,
                'CPF do Cliente Autorizado'
            );
        }

        $this->aAutXML[] = $autXML;
        return $autXML;
    }

    /**
     * taginfAdic
     * Grupo de Informações Adicionais
     * tag MDFe/infMDFe/infAdic (opcional)
     *
     * @param  stdClass $std
     * @return DOMElement
     */
    public function taginfAdic(stdClass $std)
    {
        $infAdic = $this->dom->createElement("infAdic");
        $this->dom->addChild(
            $infAdic,
            "infAdFisco",
            $std->infAdFisco,
            false,
            "Informações Adicionais de Interesse do Fisco"
        );
        $this->dom->addChild(
            $infAdic,
            "infCpl",
            $std->infCpl,
            false,
            "Informações Complementares de interesse do Contribuinte"
        );
        $this->infAdic = $infAdic;
        return $infAdic;
    }

    /**
     * Gera as tags para o elemento: infRespTec (Grupo de informações para informação do responsavel tecnico pelo sistema de emissão DF-e) e adiciona ao grupo infMDFe
     * Nível: 1
     *
     * @return \DOMElement
     */
    public function taginfRespTec($std)
    {
        $identificador = '# <infRespTec> - ';
        $this->infRespTec = $this->dom->createElement('infRespTec');
        $this->dom->addChild(
            $this->infRespTec,
            'CNPJ',
            $std->CNPJ,
            true,
            $identificador . 'CNPJ responsável'
        );
        $this->dom->addChild(
            $this->infRespTec,
            'xContato',
            $std->xContato,
            true,
            $identificador . 'Contato responsável'
        );
        $this->dom->addChild(
            $this->infRespTec,
            'email',
            $std->email,
            true,
            $identificador . 'E-mail responsavel'
        );
        $this->dom->addChild(
            $this->infRespTec,
            'fone',
            $std->fone,
            true,
            $identificador . 'Telefone responsavel'
        );
        return $this->infRespTec;
    }

    /**
     * Informações suplementares do MDF-e
     * @param stdClass $std
     * @return DOMElement
     */
    public function taginfMDFeSupl()
    {
        $infMDFeSupl = $this->dom->createElement("infMDFeSupl");
        $this->infMDFeSupl = $infMDFeSupl;
        return $infMDFeSupl;
    }
    /**
     * Metodo responsavel para montagem da tag ingPag - Informações do Pagamento do Frete
     *
     * @param stdClass $std
     * @return DOMElement
     * @throws RuntimeException
     */
    public function taginfPag(stdClass $std)
    {
        $possible = [
            'xNome',
            'CPF',
            'CNPJ',
            'idEstrangeiro',
            'Comp',
            'vContrato',
            'indPag',
            'infPrazo',
            'infBanc'
        ];
        $std = $this->equilizeParameters($std, $possible);
        $infPag = $this->dom->createElement("infPag");
        $identificador = '[4] <infPag> - ';
        $this->dom->addChild(
            $infPag,
            "xNome",
            $std->xNome,
            true,
            $identificador . "Nome do responsável pelo pgto"
        );
        if (!empty($std->CPF)) {
            $this->dom->addChild(
                $infPag,
                "CPF",
                $std->CPF,
                true,
                $identificador . "Número do CPF do responsável pelo pgto"
            );
        } elseif (!empty($std->CNPJ)) {
            $this->dom->addChild(
                $infPag,
                "CNPJ",
                $std->CNPJ,
                true,
                $identificador . "Número do CNPJ do responsável pelo pgto"
            );
        } else {
            $this->dom->addChild(
                $infPag,
                "idEstrangeiro",
                $std->idEstrangeiro,
                true,
                $identificador . "Identificador do responsável pelo pgto em caso de ser estrangeiro"
            );
        }
        foreach ($std->Comp as $value) {
            $this->dom->appChild($infPag, $this->compPag($value), 'Falta tag "Comp"');
        }
        $this->dom->addChild(
            $infPag,
            "vContrato",
            $std->vContrato,
            true,
            $identificador . "Valor total do contrato"
        );
        $this->dom->addChild(
            $infPag,
            "indPag",
            $std->indPag,
            true,
            $identificador . "Indicador da Forma de Pagamento"
        );
        if ($std->indPag == 1) {
            foreach ($std->infPrazo as $value) {
                $this->dom->appChild($infPag, $this->infPrazo($value), 'Falta tag "infPrazo"');
            }
        }
        $this->dom->appChild($infPag, $this->infBanc($std->infBanc), 'Falta tag "infBanc"');
        $this->infPag[] = $infPag;
        return $infPag;
    }

    /**
     * Componentes do Pagamento do Frete
     * @param stdClass
     *
     */
    private function compPag(stdClass $std)
    {
        $possible = [
            'tpComp',
            'vComp',
            'xComp'
        ];
        $stdComp = $this->equilizeParameters($std, $possible);
        $comp = $this->dom->createElement("Comp");
        $identificador = '[4] <Comp> - ';
        $this->dom->addChild(
            $comp,
            "tpComp",
            $stdComp->tpComp,
            true,
            $identificador . "Tipo do Componente"
        );
        $this->dom->addChild(
            $comp,
            "vComp",
            $stdComp->vComp,
            true,
            $identificador . "Valor do Componente"
        );
        $this->dom->addChild(
            $comp,
            "xComp",
            $stdComp->xComp,
            false,
            $identificador . "Descrição do componente do tipo Outros"
        );
        return $comp;
    }

    /***
     * Informações do pagamento a prazo. Obs: Informar somente se indPag for à Prazo.
     *
     */
    private function infPrazo(stdClass $std)
    {
        $possible = [
            'nParcela',
            'dVenc',
            'vParcela'
        ];
        $stdPraz = $this->equilizeParameters($std, $possible);
        $prazo = $this->dom->createElement("infPrazo");
        $identificador = '[4] <infPrazo> - ';
        $this->dom->addChild(
            $prazo,
            "nParcela",
            $stdPraz->nParcela,
            false,
            $identificador . "Número da parcela"
        );
        $this->dom->addChild(
            $prazo,
            "dVenc",
            $stdPraz->dVenc,
            false,
            $identificador . "Data de vencimento da Parcela (AAAA-MMDD)"
        );
        $this->dom->addChild(
            $prazo,
            "vParcela",
            $stdPraz->vParcela,
            true,
            $identificador . "Valor da Parcela"
        );
        return $prazo;
    }

    /**
     * Informações bancárias.
     *
     */
    private function infBanc(stdClass $std)
    {
        $possible = [
            'codBanco',
            'codAgencia',
            'CNPJIPEF'
        ];
        $stdBanco = $this->equilizeParameters($std, $possible);
        $banco = $this->dom->createElement("infBanc");
        $identificador = '[4] <infBanc> - ';
        if (!empty($stdBanco->codBanco)) {
            $this->dom->addChild(
                $banco,
                "codBanco",
                $stdBanco->codBanco,
                true,
                $identificador . "Número do banco"
            );
            $this->dom->addChild(
                $banco,
                "codAgencia",
                $stdBanco->codAgencia,
                true,
                $identificador . "Número da Agência"
            );
        } else {
            $this->dom->addChild(
                $banco,
                "CNPJIPEF",
                $stdBanco->CNPJIPEF,
                true,
                $identificador . "Número do CNPJ da Instituição de pagamento Eletrônico do Frete"
            );
        }
        return $banco;
    }

    /**
     * Add QRCode Tag to signed XML from a MDFe
     * @param DOMDocument $dom
     * @return string
     */
    public function tagQRCode($chave)
    {
        $url = htmlspecialchars("https://dfe-portal.svrs.rs.gov.br/mdfe/qrCode?chMDFe={$chave}&tpAmb={$this->tpAmb}");
        $qrCodMDFe =  $this->dom->addChild(
            $this->infMDFeSupl,
            'qrCodMDFe',
            $url,
            true, 'QRCode de consulta'
        );
        $this->qrCodMDFe = $qrCodMDFe;
        return $qrCodMDFe;
    }

    /**
     * buildMDFe
     * Tag raiz da MDFe
     * tag MDFe DOMNode
     * Função chamada pelo método [ monta ]
     *
     * @return DOMElement
     */
    protected function buildMDFe()
    {
        if (empty($this->MDFe)) {
            $this->MDFe = $this->dom->createElement("MDFe");
            $this->MDFe->setAttribute("xmlns", "http://www.portalfiscal.inf.br/mdfe");
        }
        return $this->MDFe;
    }

    /**
     * Remonta a chave da NFe de 44 digitos com base em seus dados
     * já contidos na NFE.
     * Isso é útil no caso da chave informada estar errada
     * se a chave estiver errada a mesma é substituida
     * @param Dom $dom
     * @return void
     */
    protected function checkMDFeKey(Dom $dom)
    {
        $infMDFe = $dom->getElementsByTagName("infMDFe")->item(0);
        $ide = $dom->getElementsByTagName("ide")->item(0);
        $emit = $dom->getElementsByTagName("emit")->item(0);
        $cUF = $ide->getElementsByTagName('cUF')->item(0)->nodeValue;
        $dhEmi = $ide->getElementsByTagName('dhEmi')->item(0)->nodeValue;
        $cnpj = $emit->getElementsByTagName('CNPJ')->item(0)->nodeValue;
        $mod = $ide->getElementsByTagName('mod')->item(0)->nodeValue;
        $serie = $ide->getElementsByTagName('serie')->item(0)->nodeValue;
        $nMDF = $ide->getElementsByTagName('nMDF')->item(0)->nodeValue;
        $tpEmis = $ide->getElementsByTagName('tpEmis')->item(0)->nodeValue;
        $cMDF = $ide->getElementsByTagName('cMDF')->item(0)->nodeValue;
        $chave = str_replace('MDFe', '', $infMDFe->getAttribute("Id"));
        $dt = new DateTime($dhEmi);

        $chaveMontada = Keys::build(
            $cUF,
            $dt->format('y'),
            $dt->format('m'),
            $cnpj,
            $mod,
            $serie,
            $nMDF,
            $tpEmis,
            $cMDF
        );

        if ($chaveMontada != $chave) {
            //throw new RuntimeException("A chave informada é diferente da chave
            //mondata com os dados [correto: $chaveMontada].");
            $ide->getElementsByTagName('cDV')->item(0)->nodeValue = substr($chaveMontada, -1);
            $infMDFe = $dom->getElementsByTagName("infMDFe")->item(0);
            $infMDFe->setAttribute("Id", "MDFe" . $chaveMontada);
            $this->chMDFe = $chaveMontada;
        }
    }

    /**
     * Informações do modal
     * tag MDFe/infMDFe/InfModal/Rodo
     * Depende de infMunDescarga
     */
    protected function buildInfModal()
    {
        $this->buildRodo();
        $this->buildAereo();
        $this->buildAquav();
        $this->buildFerrov();
    }

    /**
     * Informações do modal Rodoviário
     * tag MDFe/infMDFe/InfModal/Rodo
     * Depende de infMunDescarga
     */
    protected function buildRodo()
    {
        if (empty($this->aInfMunDescarga)) {
            return '';
        }

        //veicTracao
        $this->buildVeicTracao();

        //veicReboque
        $this->buildVeicReboque();

        //lacRodo
        if (isset($this->aLacRodo)) {
            $this->dom->addArrayChild($this->rodo, $this->aLacRodo);
        }

        if ($this->rodo->getElementsByTagName('codAgPorto')->length > 0){
            $codAgPorto = $this->rodo->removeChild($this->rodo->getElementsByTagName('codAgPorto')->item(0));

            if ($this->rodo->getElementsByTagName('lacRodo')->length > 0){
                $this->rodo->insertBefore($codAgPorto, $this->rodo->getElementsByTagName('lacRodo')->item(0));
            } else {
                $this->rodo->appendChild($codAgPorto);
            }
        }

        $this->infModal->insertBefore($this->rodo);
    }

    /**
     * Dados do Veículo com a Tração
     * tag MDFe/infMDFe/InfModal/Rodo/infANTT/valePed
     * Depende de infMunDescarga
     */
    protected function buildVeicTracao()
    {
        if (empty($this->veicTracao)) {
            return '';
        }

        //ccondutor
        if (isset($this->aCondutor)) {
            $node = $this->veicTracao->getElementsByTagName("tpRod")->item(0);
            foreach ($this->aCondutor as $ccondutor){
                $this->veicTracao->insertBefore($ccondutor, $node);
            }
        }
        $this->rodo->appendChild($this->veicTracao);
    }

    /**
     * Dados dos reboques
     * tag MDFe/infMDFe/InfModal/Rodo/infANTT/valePed
     * Depende de infMunDescarga
     */
    protected function buildVeicReboque()
    {
        if (isset($this->aVeicReboque)) {
            foreach ($this->aVeicReboque as $nItem => $veicReboque){
                $node = $this->aVeicReboque[$nItem]->getElementsByTagName("tpCar")->item(0);

                if (isset($this->aPropVeicReboque[$nItem])){
                    $this->aVeicReboque[$nItem]->insertBefore($this->aPropVeicReboque[$nItem], $node);
                }
                $this->rodo->appendChild($this->aVeicReboque[$nItem]);
            }
        }
    }

    /**
     * Informações do modal Aéreo
     * tag MDFe/infMDFe/InfModal/Rodo/Aereo
     * Depende de infMunDescarga
     */
    protected function buildAereo()
    {
        if (empty($this->aereo)){
            return '';
        }

        $this->infModal->appendChild($this->aereo);
    }

    /**
     * Informações do modal Aquaviário
     * tag MDFe/infMDFe/InfModal/Rodo/Aquav
     * Depende de infMunDescarga
     */
    protected function buildAquav()
    {
        if (empty($this->aquav)) {
            return '';
        }

        //infTermCarreg
        if (isset($this->aInfTermCarreg)) {
            $this->dom->addArrayChild($this->aquav, $this->aInfTermCarreg);
        }

        //infTermDescarreg
        if (isset($this->aInfTermDescarreg)) {
            $this->dom->addArrayChild($this->aquav, $this->aInfTermDescarreg);
        }

        //infEmbComb
        if (isset($this->aInfEmbComb)) {
            $this->dom->addArrayChild($this->aquav, $this->aInfEmbComb);
        }

        //infUnidCargaVazia
        if (isset($this->aInfUnidCargaVazia)) {
            $this->dom->addArrayChild($this->aquav, $this->aInfUnidCargaVazia);
        }

        //infUnidTranspVazia
        if (isset($this->aInfUnidTranspVazia)) {
            $this->dom->addArrayChild($this->aquav, $this->aInfUnidTranspVazia);
        }

        $this->infModal->appendChild($this->aquav);
    }

    /**
     * Informações do modal Ferroviário
     * tag MDFe/infMDFe/InfModal/Rodo/Ferrov
     * Depende de infMunDescarga
     */
    protected function buildFerrov()
    {
        if (empty($this->ferrov) && !empty($this->trem)) {
            $this->ferrov = $this->dom->createElement("ferrov");
        }
        if (empty($this->ferrov) && !empty($this->vag)) {
            $this->ferrov = $this->dom->createElement("ferrov");
        }

        //trem
        if (empty($this->ferrov)){
            return '';
        }

        $this->ferrov->appendChild($this->trem);

        //vag
        if (isset($this->aVag)) {
            $this->dom->addArrayChild($this->ferrov, $this->aVag);
        }

        $this->infModal->appendChild($this->ferrov);
    }

    /**
     * Informações dos Documentos fiscais vinculados ao manifesto
     * tag MDFe/infMDFe/infDoc
     * Depende de infMunDescarga
     */
    protected function buildInfDoc()
    {
        if (empty($this->aInfMunDescarga)) {
            return '';
        }

        if (empty($this->infDoc)) {
            $this->infDoc = $this->dom->createElement("infDoc");
        }

        foreach ($this->aInfMunDescarga as $nItem => $infMunDescarga) {

            //infCTe
            if (isset($this->aInfCTe[$nItem])) {
                $this->dom->addArrayChild($infMunDescarga, $this->aInfCTe[$nItem]);
            }

            //infNFe
            if (isset($this->aInfNFe[$nItem])) {
                $this->dom->addArrayChild($infMunDescarga, $this->aInfNFe[$nItem]);
            }

            $this->dom->appChild($this->infDoc, $infMunDescarga, "infDoc");
        }
    }
}
