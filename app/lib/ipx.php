<?php
class ipx {
	private   $cfg;
	protected $soap_client,
			  $wsdl_file;

	public function __construct($wsdl_file){
		$this->r = new dbredis();
		$this->init($wsdl_file);
	}

	public function init($wsdl_file){
		$this->cfg       = config::read('defaults', 'ipx');
		$this->wsdl_file = CONFDIR .'/' . $wsdl_file;
		$this->client    = new SoapClient($this->wsdl_file);
	}

	public function buildParams($keys, $overrides=array()){
		$vars = $this->cfg; 
		$out  = array();
		foreach ($keys as $k) {
			$out[$k] = @$vars[$k];
		}
		foreach ($overrides as $k => $v) {
			$out[$k] = $v;
		}
		return $out;
	}

	public function getElementsForMethod($method){
		$raw_file = file_get_contents($this->wsdl_file);
		$file     = str_replace('xsd:','', $raw_file);
		$dom      = new SimpleXMLElement($file);
		$out      = array();
		foreach ($dom->types->schema->element as $x){
			if (strtolower($method.'Request') == strtolower($x['name'])) {
				foreach ($x->complexType->sequence->element as $elem) {
					$out[] = (string)$elem['name'];
				}
			}
		};
		return $out;
	}

	public function makeCall($method, $overrides){
		if (!empty($overrides[0])) $overrides = $overrides[0];
		$keys     = $this->getElementsForMethod($method);
		$data     = $this->buildParams($keys, $overrides);
		$time = date('Y:m:d h:i:s');
		foreach ($data as $k => $v) {
			$this->r->publish('debug', "$time KEY $k VAL $v");
		}
		$response = $this->client->__soapCall($method, array('request' => $data)); 
		return $response;
	}
}
