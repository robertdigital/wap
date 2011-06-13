<?php
class main extends controller {
	var $layout = 'main';
	public function authorizePayment(){
		$sub = new subscription();
		return $sub->authorizePayment(array(
			'username'       => $this->getSubscriptionUser(),
			'password'       => $this->getSubscriptionPwd(),
			'consumerId'     => $this->getConsumerId(),
			'subscriptionId' => $this->getSubscriptionId(),
		));
	}
	public function index($request){
		$qs = Config::read('questions', 'questions');
		$nq = 2;
        $this->template('main/simple', array());
        /*
		$this->template('main/index', array(
			'questions' => array(
				'box'              => array_slice($qs, 0        , $nq),
				'box2'             => array_slice($qs, $nq      , $nq),
				'finallyquestions' => array_slice($qs, $nq + $nq, $nq)
			)
		));
        */
	}
	public function indexPost($request){
		$phone    = helpers::cleanPhoneNumber($_REQUEST['telefono']);
		$sms      = new sms();
		$response = $sms->sendSms($phone, config::read('free', 'messages'));
        $this->r->save('sms_response', $response);
        //$this->template('main/login', array());
        $this->template('main/simple', array());
	}
	/**
	 * This is called when the user clicks the link in the email
	 */
	public function playwin($request){
		$ident = new ident();
        try {
            $alias = $ident->getAliasForUser();
            $out   = $this->chargeUser();
        } catch (Exception $e) {
            try {
                $out   = $this->chargeUser();
            } catch (Exception $e) {
                $out = $this->oneshot();
            } 
        }
	}
	public function chargeuser2($request=array()){
		$out = $this->finalizeSubscriptionSession();
		if ($out->responseMessage !== 'Success') {
            $this->template('main/error', array('error' => "Could not finalize subscription"));
            exit();
		}
		$out = $this->authorizePayment();
		if ($out->responseMessage !== 'Success') {
            $this->template('main/error', array('error' => "Could not authorize payment"));
            exit();
		}
        $this->setSessionId($out->sessionId);
		$out = $this->capturePayment();
		if ($out->responseMessage !== 'Success') {
            $this->template('main/error', array('error' => "Could not capture payment"));
            exit();
		}
		return $out;
	}
    public function oneshot(){
        $purchase = new purchase();
        $out = $purchase->createSession();
        if ($out->responseMessage == 'Success') {
            header('Location: '. $out->redirectURL);
            exit();
        }
        //$this->template('main/purchased', array());
    }
	public function terminateSubscription($request){
		$sub      = new subscription();
		$con_id   = helpers::cleanPhoneNumber($_GET['consumerId']);
		$sub_id   = $_GET['subscriptionId'];
		$response = $sub->terminateSubscription(array(
			'consumerId'     => $con_id,
			'subscriptionId' => $sub_id
		));
	}

    // Private methods 

	private function capturePayment(){
		$sub = new subscription();
		$out = $sub->capturePayment(array(
			'username'  => $this->getSubscriptionUser(),
			'password'  => $this->getSubscriptionPwd(),
			'sessionId' => $this->getSessionId()
		));
		return $out;
	}
	private function chargeUser($tariff_class = 'EUR300ES') {
		$sub = new subscription();
		$out = $this->createSubscriptionSession();
		if ($out->responseMessage == 'Success'){
			$this->setSubscriptionSessionId($out->sessionId);
			header('Location: ' . $out->redirectURL);
			exit();
		}
	}
	private function checkStatus($user, $pwd){
		$session_id = $this->r->get('session:'.session_id());
		$ident      = new ident();
		$out        = $ident->checkStatus(array(
			'username'  => $user,
			'password'  => $pwd,
			'sessionId' => $session_id
		));
		return $out;
	}
	private function createSubscriptionSession($tariff_class='EUR300ES'){
		$sub = new subscription();
		$out = $sub->createSubscriptionSession(array(
			'tariffClass'       => $this->getSubscriptionTariff(),
			'returnURL'         => $this->getSubscriptionSessionUrl(),
			'serviceName'       => $this->getServiceName(),
			'frequencyInterval' => $this->getFrequencyInterval(),
			'username'          => $this->getSubscriptionUser(),
			'password'          => $this->getSubscriptionPwd(),
			'sessionId'         => $this->getSessionId(),
		));
		$this->setSessionId($out->sessionId);
		return $out;
	}
	private function finalizeSession($user, $pwd){
		$session_id = $this->getSessionId();
		$ident = new ident();
		$out = $ident->finalizeSession(array(
			'username'  => $user,
			'password'  => $pwd,
			'sessionId' => $session_id
		));
		return $out;
	}
	private function finalizeSubscriptionSession(){ 
		$sub = new subscription();
		$out = $sub->finalizeSubscriptionSession(array(
			'sessionId' => $this->getSubscriptionSessionId(),
			'username'  => $this->getSubscriptionUser(),
			'password'  => $this->getSubscriptionPwd()
		));
		$this->setConsumerId($out->consumerId);
        $this->setSubscriptionId($out->subscriptionId);
		return $out;
	}
	private function getConsumerId(){
		return $this->r->get('consumer_id:'.session_id());
	}
	private function getFrequencyInterval(){
		$details = config::read('defaults', 'ipx');
		return $details['frequency_interval'];
	}
	private function getServiceName(){
		$details = config::read('defaults', 'ipx');
		return $details['service_name'];
	}
	private function getSessionId(){
		return $this->r->get('session:'.session_id());
	}
    private function getSubscriptionId(){
        $id = $this->r->get('subscription:'.session_id());
        return $id;
    }
	private function getSubscriptionSessionId(){
		return $this->r->get('subscription_session:'.session_id());
	}
	private function getSubscriptionPwd(){
		$details = config::read('defaults', 'ipx');
		return $details['password2'];
	}
	private function getSubscriptionUser(){
		$details = config::read('defaults', 'ipx');
		return $details['username2'];
	}
	private function getSubscriptionSessionUrl(){
		$details = config::read('defaults', 'ipx');
		return $details['subscription_url'];
	}
	private function getSubscriptionTariff(){
		$details = config::read('defaults', 'ipx');
		return $details['subscription_tariff'];
	}
    private function oneshot2($req){
        $purchase = new purchase();
        $out = $this->oneshotCheckStatus();
        $out = $this->oneshotFinalizeSession();
        $this->template('main/simple');
    }
    private function oneshotCheckStatus(){
        $purchase = new purchase();
        $out = $purchase->checkStatus();
        if ($out->responseMessage != 'Success') {
            $this->template('main/error', array(
                'error' => 'Unable to complete single purchase'
            ));
            exit();
        }
    }
    private function oneshotFinalizeSession(){
        $purchase = new purchase();
        $out = $purchase->finalizeSession();
        if ($out->responseMessage != 'Success') {
            $this->template('main/error', array(
                'error' => 'Unable to finalize single purchase'
            ));
            exit();
        }
    }
	private function setConsumerId($consumer_id){
		return $this->r->set('consumer_id:'.session_id(), $consumer_id);
	}
	private function setSessionId($session_id) {
		return $this->r->set('session:'.session_id(), $session_id);
	}
	private function sendInitialSms($phone) {
		$msg    = config::read('free', 'messages');
		$sms    = new sms();
		$out    = $sms->sendSms($phone, $msg, 'EUR0ES');
		$choice = $out->responseMessage == 'Success';
		$this->r->recordEvent('send_sms', $phone, $out);
		$headings = array('Rejected', 'Accepted');
		$messages = array('no'      , ''        );
		$this->template('main/activated', array(
			'response' => $out,
			'number'   => $phone,
			'heading'  => $headings[$choice],
			'message'  => $messages[$choice]
		));
	}
	private function setSubscriptionId($id){
		return $this->r->set('subscription:'.session_id(), $id);
	}
	private function setSubscriptionSessionId($session_id){
		return $this->r->set('subscription_session:'.session_id(), $session_id);
	}
	private function setConsumerIdForSubscriptionId($out){
		$this->r->set('consumer_id:'.$out->consumerId.':subscription_id', $out->subscriptionId);
		$this->r->set('subscription_id:'.$out->subscriptionId.':consumer_id', $out->consumerId);
		
	}
}
