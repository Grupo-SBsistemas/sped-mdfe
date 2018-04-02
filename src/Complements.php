<?php

namespace NFePHP\MDFe;

use NFePHP\Common\Strings;
use NFePHP\MDFe\Common\Standardize;
use NFePHP\MDFe\Exception\DocumentsException;
use DOMDocument;

class Complements
{
    protected static $urlPortal = 'http://www.portalfiscal.inf.br/mdfe';
    /**
     * Authorize document adding his protocol
     * @param string $request
     * @param string $response
     * @return string
     */
    public static function toAuthorize($request, $response)
    {
        $st = new Standardize();
        $key = ucfirst($st->whichIs($request));
        if ($key != 'MDFe' && $key != 'EventoMDFe') {
            //wrong document, this document is not able to recieve a protocol
            throw DocumentsException::wrongDocument(0, $key);
        }
        $func = "add".$key."Protocol";
        return self::$func($request, $response);
    }
   
    /**
     * Add cancel protocol to a autorized MDFe
     * if event is not a cancellation will return
     * the same autorized MDFe passing
     * NOTE: This action is not necessary, I use only for my needs to
     *       leave the MDFe marked as Canceled in order to avoid mistakes
     *       after its cancellation.
     * @param  string $mdfe content of autorized MDFe XML
     * @param  string $cancelamento content of SEFAZ response
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function cancelRegister($mdfe, $cancelamento)
    {
        $procXML = $mdfe;
        $dommdfe = new DOMDocument('1.0', 'utf-8');
        $dommdfe->formatOutput = false;
        $dommdfe->preserveWhiteSpace = false;
        $dommdfe->loadXML($mdfe);
        $nodemdfe = $dommdfe->getElementsByTagName('MDFe')->item(0);
        $proMDFe = $dommdfe->getElementsByTagName('protMDFe')->item(0);
        if (empty($proMDFe)) {
            //not protocoladed mdfe
            throw DocumentsException::wrongDocument(1);
        }
        $chaveMDFe = $proMDFe->getElementsByTagName('chMDFe')->item(0)->nodeValue;
        $tpAmb = $dommdfe->getElementsByTagName('tpAmb')->item(0)->nodeValue;
        $domcanc = new DOMDocument('1.0', 'utf-8');
        $domcanc->formatOutput = false;
        $domcanc->preserveWhiteSpace = false;
        $domcanc->loadXML($cancelamento);
        $eventos = $domcanc->getElementsByTagName('retEvento');
        foreach ($eventos as $evento) {
            $infEvento = $evento->getElementsByTagName('infEvento')->item(0);
            $cStat = $infEvento->getElementsByTagName('cStat')
                ->item(0)
                ->nodeValue;
            $nProt = $infEvento->getElementsByTagName('nProt')
                ->item(0)
                ->nodeValue;
            $chaveEvento = $infEvento->getElementsByTagName('chMDFe')
                ->item(0)
                ->nodeValue;
            $tpEvento = $infEvento->getElementsByTagName('tpEvento')
                ->item(0)
                ->nodeValue;
            if (in_array($cStat, ['135', '136', '155'])
                && $tpEvento == '110111'
                && $chaveEvento == $chaveMDFe
            ) {
                $proNFe->getElementsByTagName('cStat')
                    ->item(0)
                    ->nodeValue = '101';
                $proNFe->getElementsByTagName('nProt')
                    ->item(0)
                    ->nodeValue = $nProt;
                $proNFe->getElementsByTagName('xMotivo')
                    ->item(0)
                    ->nodeValue = 'Cancelamento de MDF-e homologado';
                $procXML = Strings::clearProtocoledXML($dommdfe->saveXML());
                break;
            }
        }
        return $procXML;
    }
    
    /**
     * Authorize MDFe
     * @param string $request
     * @param string $response
     * @return string
     * @throws InvalidArgumentException
     */
    protected static function addMDFeProtocol($request, $response)
    {
        $req = new DOMDocument('1.0', 'UTF-8');
        $req->preserveWhiteSpace = false;
        $req->formatOutput = false;
        $req->loadXML($request);
        
        $mdfe = $req->getElementsByTagName('MDFe')->item(0);
        $infMDFe = $req->getElementsByTagName('infMDFe')->item(0);
        $versao = $infMDFe->getAttribute("versao");
        $chave = preg_replace('/[^0-9]/', '', $infMDFe->getAttribute("Id"));
        $digMDFe = $req->getElementsByTagName('DigestValue')
            ->item(0)
            ->nodeValue;
        $ret = new DOMDocument('1.0', 'UTF-8');
        $ret->preserveWhiteSpace = false;
        $ret->formatOutput = false;
        $ret->loadXML($response);
        $retProt = $ret->getElementsByTagName('protMDFe');
        if (!isset($retProt)) {
            throw DocumentsException::wrongDocument(3, "&lt;protMDFe&gt;");
        }
        $digProt = '000';
        foreach ($retProt as $rp) {
            $infProt = $rp->getElementsByTagName('infProt')->item(0);
            $cStat  = $infProt->getElementsByTagName('cStat')->item(0)->nodeValue;
            $xMotivo = $infProt->getElementsByTagName('xMotivo')->item(0)->nodeValue;
            $dig = $infProt->getElementsByTagName("digVal")->item(0);
            $key = $infProt->getElementsByTagName("chMDFe")->item(0)->nodeValue;
            if (isset($dig)) {
                $digProt = $dig->nodeValue;
                if ($digProt == $digMDFe && $chave == $key) {
                    //100 Autorizado
                    //150 Autorizado fora do prazo
                    //110 Uso Denegado
                    //205 NFe Denegada
                    //302 Uso denegado por irregularidade fiscal do destinatário
                    $cstatpermit = ['100', '150', '110', '205', '302'];
                    if (!in_array($cStat, $cstatpermit)) {
                        throw DocumentsException::wrongDocument(4, "[$cStat] $xMotivo");
                    }
                    return self::join(
                        $req->saveXML($mdfe),
                        $ret->saveXML($rp),
                        'mdfeProc',
                        $versao
                    );
                }
            }
        }
        if ($digMDFe !== $digProt) {
            throw DocumentsException::wrongDocument(5, "Os digest são diferentes");
        }
        return $req->saveXML();
    }
    /**
     * Authorize Event
     * @param string $request
     * @param string $response
     * @return string
     * @throws InvalidArgumentException
     */
    protected static function addEventoMDFeProtocol($request, $response)
    {
        $ev = new \DOMDocument('1.0', 'UTF-8');
        $ev->preserveWhiteSpace = false;
        $ev->formatOutput = false;
        $ev->loadXML($request);
        //extrai numero do lote do envio
        $envChave = $ev->getElementsByTagName('chMDFe')->item(0)->nodeValue;
        //extrai tag evento do xml origem (solicitação)
        $event = $ev->getElementsByTagName('eventoMDFe')->item(0);
        $versao = $event->getAttribute('versao');
        $ret = new \DOMDocument('1.0', 'UTF-8');
        $ret->preserveWhiteSpace = false;
        $ret->formatOutput = false;
        $ret->loadXML($response);
        //extrai numero do lote da resposta
        $resChave = $ret->getElementsByTagName('chMDFe')->item(0)->nodeValue;
        //extrai a rag retEvento da resposta (retorno da SEFAZ)
        $retEv = $ret->getElementsByTagName('retEventoMDFe')->item(0);
        $cStat  = $retEv->getElementsByTagName('cStat')->item(0)->nodeValue;
        $xMotivo = $retEv->getElementsByTagName('xMotivo')->item(0)->nodeValue;
        $tpEvento = $retEv->getElementsByTagName('tpEvento')->item(0)->nodeValue;
        $cStatValids = ['135', '136'];
        if ($tpEvento == '110111') {
            $cStatValids[] = '155';
        }
        if (!in_array($cStat, $cStatValids)) {
            throw DocumentsException::wrongDocument(4, "[$cStat] $xMotivo");
        }
        if ($envChave !== $resChave) {
            throw DocumentsException::wrongDocument(
                5,
                "Os numeros de chave dos documentos são diferentes."
            );
        }
        return self::join(
            $ev->saveXML($event),
            $ret->saveXML($retEv),
            'procEventoMDFe',
            $versao
        );
    }
    /**
     * Join the pieces of the source document with those of the answer
     * @param string $first
     * @param string $second
     * @param string $nodename
     * @param string $versao
     * @return string
     */
    protected static function join($first, $second, $nodename, $versao)
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
                . "<$nodename versao=\"$versao\" "
                . "xmlns=\"".self::$urlPortal."\">";
        $xml .= $first;
        $xml .= $second;
        $xml .= "</$nodename>";
        return $xml;
    }
}