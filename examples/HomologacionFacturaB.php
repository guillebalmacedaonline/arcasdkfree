<?php
/**
 * Prueba en homologacion: Factura B a consumidor final con CondicionIVAReceptorId = 5 (RG ARCA 5616).
 *
 * Requiere un certificado de homologacion con acceso a wsfe. Se configura por variables de entorno:
 *
 *   AFIP_CUIT        CUIT del emisor (obligatorio)
 *   AFIP_RES_FOLDER  Carpeta con el certificado y la clave (obligatorio, con "/" final)
 *   AFIP_TA_FOLDER   Carpeta escribible para los TA (opcional, por defecto AFIP_RES_FOLDER)
 *   AFIP_CERT        Nombre del certificado (opcional, por defecto "{CUIT}_cert")
 *   AFIP_KEY         Nombre de la clave (opcional, por defecto "{CUIT}_key")
 *   AFIP_PTO_VTA     Punto de venta (opcional, por defecto 1)
 *
 * Uso: php examples/HomologacionFacturaB.php   (sale con codigo 0 si se obtuvo un CAE)
 */
include __DIR__.'/../src/Afip.php';

$cuit = getenv('AFIP_CUIT');
$res  = getenv('AFIP_RES_FOLDER');

if (!$cuit || !$res) {
	fwrite(STDERR, "Faltan AFIP_CUIT y/o AFIP_RES_FOLDER\n");
	exit(2);
}

$ptoVta = (int) (getenv('AFIP_PTO_VTA') ?: 1);
$tipo   = 6; // Factura B

$afip = new Afip(array(
	'CUIT' 			=> (float) $cuit,
	'cert' 			=> getenv('AFIP_CERT') ?: $cuit.'_cert',
	'key' 			=> getenv('AFIP_KEY') ?: $cuit.'_key',
	'production' 	=> FALSE,
	'res_folder' 	=> $res,
	'ta_folder' 	=> getenv('AFIP_TA_FOLDER') ?: $res,
	'soap_timeout' 	=> 30,
));

$eb = $afip->ElectronicBilling;

// Condiciones de IVA del receptor disponibles para Factura B
$condiciones = $eb->GetCondicionIvaReceptorTypes('B');
echo "Condiciones IVA receptor para clase B:\n";
foreach ($condiciones as $c) {
	echo "  {$c->Id} - {$c->Desc}\n";
}

$numero = $eb->GetLastVoucher($ptoVta, $tipo) + 1;

$data = array(
	'CantReg' 		=> 1,
	'PtoVta' 		=> $ptoVta,
	'CbteTipo' 		=> $tipo,
	'Concepto' 		=> 1,
	'DocTipo' 		=> 99, // Consumidor final
	'DocNro' 		=> 0,
	'CbteDesde' 	=> $numero,
	'CbteHasta' 	=> $numero,
	'CbteFch' 		=> intval(date('Ymd')),
	'ImpTotal' 		=> 121,
	'ImpTotConc' 	=> 0,
	'ImpNeto' 		=> 100,
	'ImpOpEx' 		=> 0,
	'ImpIVA' 		=> 21,
	'ImpTrib' 		=> 0,
	'MonId' 		=> 'PES',
	'MonCotiz' 		=> 1,
	'CondicionIVAReceptorId' => 5, // Consumidor final
	'Iva' 			=> array(
		array('Id' => 5, 'BaseImp' => 100, 'Importe' => 21) // 21%
	),
);

try {
	$out = $eb->CreateVoucher($data);
} catch (SoapFault $e) {
	fwrite(STDERR, "Resultado incierto (SoapFault): ".$e->getMessage()."\n");
	exit(3);
} catch (Exception $e) {
	fwrite(STDERR, "AFIP rechazo el comprobante: ".$e->getMessage()." (code ".$e->getCode().")\n");
	exit(1);
}

// Verifica que el elemento viaje en el XML enviado
$xml = $eb->soap_client->__getLastRequest();
if (!preg_match('#<[^>]*CondicionIVAReceptorId>5</#', $xml)) {
	fwrite(STDERR, "CondicionIVAReceptorId NO viajo en el XML enviado\n");
	exit(1);
}

echo "CondicionIVAReceptorId viajo en el XML: OK\n";
echo "Comprobante $numero -> CAE {$out['CAE']} (vto {$out['CAEFchVto']})\n";

if (!empty($out['Observaciones'])) {
	echo "Observaciones:\n";
	foreach ($out['Observaciones'] as $o) {
		echo "  ({$o['Code']}) {$o['Msg']}\n";
	}
}

exit(empty($out['CAE']) ? 1 : 0);
