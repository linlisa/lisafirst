<?php
use Phalcon\Config;
class Base extends Phalcon\Mvc\Controller {

	public $_status;
	public $_code;
	public $_msg;
	public $_request;
	public $_param;
	public $_output_additional;
	public $_site;
	public $_group;
	public $_sgid; 
	public $_user;
	public $_activecode;
	public $_apiconfig;
	function onConstruct() {
		include (__DIR__ . "/../../api/system.conf.php");
		$this -> _apiconfig = new Config($api);
		$this -> _request = $this -> request -> get();
		$this -> _status = true;
		$this -> _code = 0;
		$this -> _output_additional = array();
		$this -> _msg = $this -> _apiconfig -> msg;
		$this -> _site = array("ellemen" => 3, "ellechina" => 2, "ellefit" => 6, "femina" => 7, "mc" => 5, "cad" => 4);
		$this -> _group = array("ELLE" => 8, "FEMINA" => 11, "MC" => 10, "CAD" => 9);
		$this -> _sgid = array("2" => 8, "3" => 8, "4" => 9, "5" => 10, "6" => 8, "7" => 11);
		$this->_activecode=array("2"=>1,"3"=>1,"4"=>1,"5"=>1,"6"=>1,"7"=>1);//"0"=>需要激活 "1"=>不需要激活,自动激活
		$this -> _param = $this -> _apiconfig -> param;
		$this -> _checkParamExist();
	}
	

	protected function _checkUser($gid, $secretcode,$sgid) {
		$where = array("conditions" => "gid='$gid' and sgid='$sgid'");
		$ubaseinfo = UBASE::findFirst($where);
		if ($ubaseinfo) {
			//var_dump($secretcode,$user->secretcode);
			if ($ubaseinfo -> secretcode == $secretcode) {
				$this -> _output_additional['mobile'] = $ubaseinfo -> mobile;
				$this->_output_additional['block']=$ubaseinfo->block;
				$uinfos = UINFO::findFirst($where);

				
				
				return $uinfos;
			} else {
				$this -> _code = 10014;
			}

		} else {
			$this -> _code = 40002;
		}
		$this -> _show();
	}
	
	protected function _getSidFromSite($site){
		if ($site){
			
		}
	}

	protected function _sendRegisterMess($site, $mobile) {
		switch ($site) {
			case 'ellemen' :
				@$this -> _sendMessage($mobile, "恭喜你成功注册ELLEMEN CLUB，在这里你将跟我们一起玩转时尚圈，打开全新视野，学会更体面地生活。期待跟你产生更多的交流，请多指教！");
				break;
			case 'ellefit' :
				@$this -> _sendMessage($mobile, "恭喜你成功注册ELLEfit ，加入时髦人的运动生活圈。ELLEfit将持续精选新潮运动项目，引领运动潮流风尚；携手明星与达人，共同打造时髦人的运动生活圈。Let’s ELLEfit!");
				break;
			case 'cad' :
				@$this -> _sendMessage($mobile, "恭喜你成功注册CAD，在这里你将跟我们一起玩转时尚圈，打开全新视野，学会更体面地生活。期待跟你产生更多的交流，请多指教！");
				break;
			case 'femina' :
				@$this -> _sendMessage($mobile, "恭喜你成功注册Femina，在这里你将跟我们一起玩转时尚圈，打开全新视野，学会更体面地生活。期待跟你产生更多的交流，请多指教！");
				break;
			case 'mc' :
				@$this -> _sendMessage($mobile, "恭喜你成功注册嘉人，在这里你将跟我们一起玩转时尚圈，打开全新视野，学会更体面地生活。期待跟你产生更多的交流，请多指教！");
				break;
			default :
				break;
		}

	}

	protected function _sendGetvcodeMess($site, $code, $mobile) {
		switch($site) {
			case 'ellemen' :
				@$this -> _sendMessage($mobile, "ELLEMEN CLUB验证码：" . $code . "，温馨提示：请在10分钟内完成输入，非本人操作请忽略本条信息。");
				break;
			case 'ellefit' :
				@$this -> _sendMessage($mobile, "ELLEfit 会员验证码：" . $code . ", 温馨提示： 请在10分钟内完成输入，非本人操作请忽略本信息。");
				break;
			case 'elle' :
				@$this -> _sendMessage($mobile, "ELLE 会员验证码：" . $code . ", 温馨提示： 请在10分钟内完成输入，非本人操作请忽略本信息。");
				break;
			case 'cad' :
				@$this -> _sendMessage($mobile, "CAD 验证码：" . $code . ", 温馨提示： 请在10分钟内完成输入，非本人操作请忽略本信息。");
				break;
			default :
				break;
		}
	}


	protected function _sendMessage($mobile, $message = '') {
		$orig_message = $message;
		$orig_mobile = $mobile;
		$message = iconv('utf-8', 'gbk', $message);
		preg_match_all('/(.)/s', $message, $bytes);
		$bytes = array_map('ord', $bytes[1]);
		for ($i = 0; $i < count($bytes); $i++) {
			$bytes[$i] = dechex($bytes[$i]);
		}
		$message = implode("", $bytes);

		//var_dump($mobile);
		if (is_array($mobile)) {
			$mobile = implode(",", $mobile);
			$url = "http://esms100.10690007.net/sms/mt?command=MULTI_MT_REQUEST&spid=9265&sppassword=bjgg9265&das=$mobile&dc=15&sm=$message";
		} else {
			$url = "http://esms100.10690007.net/sms/mt?command=MT_REQUEST&spid=9265&sppassword=bjgg9265&da=86$mobile&dc=15&sm=$message";
		}
		$r = file_get_contents($url);
		//var_dump($r);exit();
		$r = explode("&", $r);

		if ($r[4] == 'mterrcode=000') {
			if (is_array($orig_mobile)) {
				$mtmsgids=array();
				$mtmsgids=explode(',', substr($r[2],strpos($r[2],"=")+1));
				foreach ($orig_mobile as $key=>$item) {
					$data = array();
					$data['mobile'] = substr($item,2,strlen($item)-2);
					$data['message'] = $orig_message;
					$data['created'] = time();
					$data['site'] = $this->_request['site'];
					$data['ip']=$this->_get_real_ip();
					//++lisa 2016/09/06
					$data['return_code']=90007;
					$data['return_message']=$r[4]."|短信发送成功";
					//++lisa 2016/09/09
					$data['mtmsgid']=$mtmsgids[$key];
					$oMessage = new Message();
					$oMessage -> save($data);
				}
			} else {
				$data = array();
				$data['mobile'] = $orig_mobile;
				$data['message'] = $orig_message;
				$data['created'] = time();
				$data['site'] = $this->_request['site'];
				$data['ip']=$this->_get_real_ip();
				//++lisa 2016/09/06
				$data['return_code']=90007;
				$data['return_message']=$r[4]."|短信发送成功";
				//++lisa 2016/09/09
				$data['mtmsgid']=substr($r[2],strpos($r[2],"=")+1);
				$oMessage = new Message();
				$oMessage -> save($data);
			}
			$this -> _code = 90007;
		} else {
			if (is_array($orig_mobile)) {
				$mtmsgids=array();
				$mtmsgids=explode(',', substr($r[2],strpos($r[2],"=")+1));
				foreach ($orig_mobile as $key=>$item) {
					$data = array();
					$data['mobile'] = substr($item,2,strlen($item)-2);
					$data['message'] = $orig_message;
					$data['created'] = time();
					$data['site'] = $this->_request['site'];
					//++lisa 2016/09/06
					$data['return_code']=90008;
					$data['return_message']=$r[4]."短信发送失败";
					//++lisa 2016/09/09
					$data['mtmsgid']=$mtmsgids[$key];
					$oMessage = new Message();
					$oMessage -> save($data);
				}
			} else {
				$data = array();
				$data['mobile'] = $orig_mobile;
				$data['message'] = $orig_message;
				$data['created'] = time();
				$data['site'] = $this->_request['site'];
				//++lisa 2016/09/06
				$data['return_code']=90008;
				$data['return_message']=$r[4]."短信发送失败";
				//++lisa 2016/09/09
				$data['mtmsgid']=substr($r[2],strpos($r[2],"=")+1);
				$oMessage = new Message();
				$oMessage -> save($data);
			}
			$this -> _code = 90008;
		}
		return $this -> _code;
	}

	protected function _checkParamExist() {
		$action = explode("/", $this -> _request['_url']);
		$action = $action[3];
		$params = $this -> _param -> $action;
		if ($params) {
			$request = $this -> _request;
			unset($request['_url']);
			foreach ($params['required'] as $param) {
				if (!isset($request[$param])) {
					$this -> _code = 90002;
					$this -> _output_additional['remark'] = "$param is required";
					$this -> _show();
				}
			}
		} else {
			$this -> _code = 90003;
			$this->_show();
		}
		
	}

	protected function _show() {
		$this->_getHeadParams();
		$output = array();
		$output["code"] = $this -> _code;
		$output["msg"] = $this -> _msg[$this -> _code];
		foreach ($this->_output_additional as $key => $value) {
			$output[$key] = $value;
		}

		$callback = !empty($this -> _request['callback']) ? ($this -> _request['callback']) : 'callback';
		if ($callback != 'callback') {
			echo $callback . "(" . json_encode($output) . ")";
		} else {
			echo json_encode($output);
		}
		exit();
	}

	 function _get_real_ip() {
		$ip = false;
		if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
			$ip = $_SERVER["HTTP_CLIENT_IP"];
		}
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
			if ($ip) { array_unshift($ips, $ip);
				$ip = FALSE;
			}
			for ($i = 0; $i < count($ips); $i++) {
				if (!eregi("^(10|172\.16|192\.168)\.", $ips[$i])) {
					$ip = $ips[$i];
					break;
				}
			}
		}
		return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
	}

	private function _getHeadParams() {
		$r = $params = $output = array();
		$r['request'] = $this -> _request['_url'];
		foreach ($this->_request as $k => $v) {
			$params[$k] = $v;
		}
		unset($params['_url']);
		$r['param'] = json_encode($params);
		$output["code"] = $this -> _code;
		$output["msg"] = $this -> _msg[$this -> _code];
		foreach ($this->_output_additional as $key => $value) {
			$output[$key] = $value;
		}
		$r['output'] = json_encode($output);
		$r['referer'] = $_SERVER['HTTP_REFERER'];
		$r['req_time'] = $_SERVER['REQUEST_TIME'];
		$r['req_ip'] = $this->_get_real_ip();
		$r['http_user_agent']=$_SERVER['HTTP_USER_AGENT'];
		$apiReq=new ApiRequest();
		if(!$apiReq->save($r)){
			$this->_output_additional['saveReqStatus']="request saved failed";
		}
		

	}

}
?>