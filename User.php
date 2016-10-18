<?php
class User extends Base {

	//对base表和info表进行数据回滚
	private function _rowRollback($gid, $sgid) {
		$where = array("conditions" => "gid='$gid' and sgid='$sgid'");
		$resBase = UBASE::findFirst($where) -> delete();
		$resInfo = UINFO::findFirst($where) -> delete();
		if (!empty($resBase) || !empty($resInfo)) {
			//"系统错误，数据库回滚成功"
			$this -> _code = 90020;
		} else {
			//"系统错误，数据库回滚错误"
			$this -> _code = 90021;
		}
	}

	//登陆成功后返回的数据
	private function _setOutputData($gid, $sgid) {
		$where = array("conditions" => "gid='$gid' and sgid='$sgid'");
		$ubaseinfo = UBASE::findFirst($where);
		$uinfos = UINFO::findFirst($where);

		$this -> _output_additional['gid'] = $ubaseinfo -> gid;
		$this -> _output_additional['secretcode'] = $ubaseinfo -> secretcode;
		$this -> _output_additional['name'] = $uinfos -> name;
		$this -> _output_additional['username'] = $uinfos -> username;
		$this -> _output_additional['avator'] = $uinfos -> avator;
		$this -> _output_additional['block'] = $ubaseinfo -> block;

		$sid = $this -> _site[$this -> _request['site']];
		$where = array("conditions" => "gid=$gid and sid='$sid'");
		$uopenid = UOPENID::findFirst($where);
		if ($uopenid -> openid) {
			$this -> _output_additional['wx-bind-status'] = 1;
			$this -> _output_additional['wx-openid'] = $uopenid -> openid;
			$this -> _output_additional['wx-nickname'] = $uopenid -> nickname;
		} else {
			$this -> _output_additional['wx-bind-status'] = 0;
		}
	}

	//激活用户 mobile/vcode/site
	function active_user() {
		$mobile = $this -> _request['mobile'];
		$sid = $this -> _site[$this -> _request['site']];
		//$sgid = UCSITE::_getSgidBySid($sid);
		$sgid = $this -> _sgid[$sid];
		$vcode = $this -> _request['vcode'];
		$where = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
		$ubase = UBASE::findFirst($where);
		if ($ubase) {
			if (!Vcode::_checkVcode($mobile, $sid, $vcode, 4)) {
				$this -> _code = 90010;
			} else {
				$Vcode -> active = 1;
				$Vcode -> save();
				$user['sidgroup'] = $ubase -> sidgroup . ',' . $sid;
				$user['updated'] = time();
				$user['secretcode'] = $secretcode = md5($user['updated'] . $ubase -> password);
				if ($ubase -> save($user)) {
					$this -> _output_additional['gid'] = $ubase -> gid;
					$this -> _output_additional['secretcode'] = $secretcode;
					//retrun "active success"
					$this -> _code = 60002;
				} else {
					$this -> _code = 90001;
				}
			}
		} else {
			//return "no user"
			$this -> _code = 10022;
		}
		$this -> _show();
	}

	//lisa+判断用户是否已在某站点激活
	private function _userInSiteActivedOrNot($gid, $sid) {
		$sgid = $this -> _sgid[$sid];
		$where = array("conditions" => "gid='$gid' and sgid='$sgid'");
		$ubase = UBASE::findFirst($where);
		$arr_sidgroup = explode(',', $ubase -> sidgroup);
		if (!in_array($sid, $arr_sidgroup)) {
			return false;
		} else {
			return true;
		}

	}

	//"mobile","password","site","type:1 或者空走密码路线 type:2 走验证码路线"
	function login() {
		$mobile = $this -> _request['mobile'];
		$sid = $this -> _site[$this -> _request['site']];
		// $sgid = UCSITE::_getSgidBySid($sid);
		$sgid = $this -> _sgid[$sid];
		$where = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
		$ubaseinfo = UBASE::findFirst($where);
		if ($ubaseinfo) {
			$type = $this -> _request['type'];
			switch($type) {
				case '2' :
					//？需要激活？
					$vcode = $this -> _request['password'];
					if (!($Vcode = Vcode::_checkVcode($mobile, $sid, $vcode, 3))) {
						$this -> _code = 90010;
					} else {
						$Vcode -> active = 1;
						$Vcode -> save();
						//最终返回数据
						$this -> _setOutputData($ubaseinfo -> gid, $ubaseinfo -> sgid);
						$this -> _code = 10001;
					}
					break;
				case '1' :
				default :
					$password = md5($this -> _request['password'] . $mobile);
					if ($ubaseinfo -> password == $password) {
						if ($ubaseinfo -> block > 0) {
							$this -> _code = 10003;
						} else {
							//如果首次用该用户名密码登陆的site,需要激活
							$arr_sidgroup = explode(',', $ubaseinfo -> sidgroup);
							if (!in_array($sid, $arr_sidgroup)) {
								$active_code = $this -> _activecode[$sid];
								switch ($active_code) {
									case '1' :
										//人工激活
										$ubaseinfo -> sidgroup = $ubaseinfo -> sidgroup . ',' . $sid;
										if ($ubaseinfo -> save()) {
											$this -> _setOutputData($ubaseinfo -> gid, $ubaseinfo -> sgid);
											$this -> _code = 10001;
										} else {
											$this -> _code = 90001;
										}

										break;
									case "0" :
										//跳转页面激活 return 'need to be activited'
										$this -> _code = 60001;
								}
							} else {
								//最终返回数据
								$this -> _setOutputData($ubaseinfo -> gid, $ubaseinfo -> sgid);
								$this -> _code = 10001;
							}
						}
					} else {
						//return "password error"
						$this -> _code = 10021;
					}
					break;
			}
		} else {
			//return "no user"
			$this -> _code = 10022;
		}
		$this -> _show();
	}

	//mobile/password/site/vcode
	function register() {
		$ubase = array();
		$ubase['mobile'] = $mobile = $this -> _request['mobile'];
		$ubase['password'] = md5($this -> _request['password'] . $mobile);
		$sid = $this -> _site[$this -> _request['site']];
		$ubase['sgid'] = $sgid = $this -> _sgid[$sid]; ;
		$vcode = $this -> _request['vcode'];
		$where_base = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
		$tmpubase = UBASE::findFirst($where_base);
		if ($tmpubase) {
			$this -> _code = 20002;
		} else {
			if (!($Vcode = Vcode::_checkVcode($mobile, $sid, $vcode, 1))) {
				$this -> _code = 90010;
			} else {
				$Vcode -> active = 1;
				$Vcode -> save();
				//生成唯一gid
				$Ucgid = new UCGID();
				$tmpdata['time'] = time();
				if ($Ucgid -> save($tmpdata)) {
					$uinfo['gid'] = $ubase['gid'] = $gid = $Ucgid -> gid;
					$ubase['created'] = $ubase['updated'] = time();
					$ubase['block'] = 0;
					$ubase['secretcode'] = md5($ubase['updated'] . $ubase['password']);
					$ubase['from_sid'] = $ubase['sidgroup'] = $sid;
					$uinfo['sgid'] = $ubase['sgid'];
					$uinfo['created'] = $uinfo['updated'] = time();
					//保存ubase数据进入n_user_base和n_uc_user_info
					$Ubase = new UBASE();
					$Uinfo = new UINFO();
					if ($Ubase -> save($ubase) && $Uinfo -> save($uinfo)) {
						//+0927 by lisa 向手机号归属地表添加数据
						$ump = array();
						$ump['gid'] = $gid;
						$ump['mobile'] = $mobile;
						$ump['created'] = $ump['updated'] = time();
						list($ump['resultcode'], $ump['reason'], $ump['province'], $ump['city'], $ump['areacode'], $ump['zip'], $ump['mobile_company'], $ump['card'], $ump['errcode']) = $this -> _get_mobile_province_resluts($mobile);
						$UMobilePro = new UMobileProvince();
						$UMobilePro -> save($ump);
						//++
						$ubaseinfo = UBASE::findFirst($where_base);
						$this -> _output_additional['gid'] = $ubaseinfo -> gid;
						$this -> _output_additional['secretcode'] = $ubaseinfo -> secretcode;
						//根据gid与sgid获取name与username
						$where_info = array("conditions" => "gid='$ubaseinfo->gid' and sgid='$ubaseinfo->sgid'");
						$uinfos = UINFO::findFirst($where_info);
						$this -> _output_additional['name'] = $uinfos -> name;
						$this -> _output_additional['username'] = $uinfos -> username;
						$this -> _sendRegisterMess($this -> _request['site'], $mobile);
						$this -> _code = 20001;
					} else {
						//rollback,delete -90020rollback success! 90021-rollback failed！
						$this -> _rowRollback($Ucgid -> gid, $sgid);
					}
				} else {
					$this -> _code = 90001;
				}
			}
		}
		$this -> _show();
	}

	private function _get_mobile_province_resluts($mobile) {
		//$mobile = $this -> _request['mobile'];
		$getMobileProvince = "http://apis.juhe.cn/mobile/get?phone=" . $mobile . "&key=53a84d255a7922a6a3848cbba4e79633";
		$rjson = file_get_contents($getMobileProvince);
		$jsondecode = json_decode($rjson);
		$r = get_object_vars($jsondecode);

		$resultcode = $r['resultcode'];
		$reason = $r['reason'];
		$province = $r['result'] -> province;
		$city = $r['result'] -> city;
		$areacode = $r['result'] -> areacode;
		$zip = $r['result'] -> zip;
		$company = $r['result'] -> company;
		$card = $r['result'] -> card;
		$error_code = $r['error_code'];
		return array($resultcode, $reason, $province, $city, $areacode, $zip, $company, $card, $error_code);
	}

	//获取信息gid/secretcode/site
	function getinfo() {
		$gid = $this -> _request['gid'];
		$secretcode = $this -> _request['secretcode'];
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];
		if ($this -> _userInSiteActivedOrNot($gid, $sid)) {
			if ($uinfos = $this -> _checkUser($gid, $secretcode, $sgid)) {
				$this -> _code = 40001;
				unset($uinfos -> sgid);
				unset($uinfos -> created);
				unset($uinfos -> updated);
				unset($uinfos -> province);
				unset($uinfos -> city);
				unset($uinfos -> district);

				foreach ($uinfos as $key => $value) {
					$this -> _output_additional[$key] = $value;
				}
				if ($this -> _output_additional['postcode'] != 0) {
					$this -> _output_additional['pid'] = substr($this -> _output_additional['postcode'], 0, 2) . "0000";
					$this -> _output_additional['cid'] = substr($this -> _output_additional['postcode'], 0, 4) . "00";
					$this -> _output_additional['did'] = $this -> _output_additional['postcode'];
				}
				$where = array("conditions" => "gid=$gid and sid='$sid'");
				$uopenid = UOPENID::findFirst($where);
				if ($uopenid -> openid) {
					$this -> _output_additional['wx-bind-status'] = 1;
					$this -> _output_additional['wx-openid'] = $uopenid -> openid;
					$this -> _output_additional['wx-nickname'] = $uopenid -> nickname;
				} else {
					$this -> _output_additional['wx-bind-status'] = 0;
				}

			}
		} else {
			$this -> _code = 60001;
		}

		$this -> _show();
	}

	//更新信息 "gid","secretcode","postcode","address","sex","nickname","name","block","height","weight",'birthday','site'++lisa 2016/09/07 ,'email'
	function updateinfo_base() {
		$gid = $this -> _request['gid'];
		$secretcode = $this -> _request['secretcode'];
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];
		$where = array("conditions" => "secretcode = '$secretcode' and gid='$gid' and sgid='$sgid'");
		if ($this -> _userInSiteActivedOrNot($gid, $sid)) {
			if (($uinfos = $this -> _checkUser($gid, $secretcode, $sgid)) && ($ubases = UBASE::findFirst($where))) {
				$action = __FUNCTION__;
				$params = $this -> _param -> $action;
				foreach ($params['required'] as $field) {
					$uinfo[$field] = $this -> _request[$field];
				}
				$uinfo['updated'] = $ubase['updated'] = time();
				$ubase['block'] = $uinfo['block'];
				unset($uinfo['block']);
				$uinfo['email'] = !empty($this -> _request['email']) ? $this -> _request['email'] : "";
				$ubase['secretcode'] = $secretcode = md5($ubase['updated'] . $ubases -> password);
				if ($uinfos -> save($uinfo) && $ubases -> save($ubase)) {
					$where_base = array("conditions" => "secretcode = '$secretcode' and gid='$gid' and sgid='$sgid'");
					$ubaseinfo = UBASE::findFirst($where_base);
					$this -> _output_additional['gid'] = $ubaseinfo -> gid;
					$this -> _output_additional['secretcode'] = $ubaseinfo -> secretcode;
					$where_info = array("conditions" => "gid='$ubaseinfo->gid' and sgid='$ubaseinfo->sgid'");
					$new_uinfos = UINFO::findFirst($where_info);
					$this -> _output_additional['name'] = $new_uinfos -> name;
					$this -> _output_additional['username'] = $new_uinfos -> username;
					$this -> _output_additional['email'] = $new_uinfos -> email;
					$this -> _code = 40003;
				} else {
					//rollback delete
					$this -> _rowRollback($gid, $sgid);
				}
			}
		} else {
			$this -> _code = 60001;
		}

		$this -> _show();
	}

	//更新头像 "gid","secretcode","avator"，site
	function updateinfo_avator() {
		$gid = $this -> _request['gid'];
		$secretcode = $this -> _request['secretcode'];
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];
		if ($this -> _userInSiteActivedOrNot($gid, $sid)) {
			if ($userinfo = $this -> _checkUser($gid, $secretcode, $sgid)) {
				//var_dump($avator);
				if (substr($this -> _request['avator'], 0, 24) == "http://cp.hearst.com.cn/") {

					$userinfo -> avator = $this -> _request['avator'];
					$where_base = array("conditions" => "gid='$gid' and sgid='$sgid'");
					$ubase = UBASE::findFirst($where_base);
					$userinfo -> updated = $ubase -> updated = time();
					$ubase -> secretcode = $secretcode = md5($ubase -> updated . $ubase -> password);
					if ($userinfo -> save() && $ubase -> save()) {
						$this -> _output_additional['gid'] = $ubase -> gid;
						$this -> _output_additional['secretcode'] = $ubase -> secretcode;
						$this -> _output_additional['name'] = $userinfo -> name;
						$this -> _output_additional['username'] = $userinfo -> username;
						$this -> _code = 40006;
					} else {
						//rollback delete
						$this -> _rowRollback($gid, $sgid);
					}
				} else {
					$this -> _code = 90011;
				}
			}
		} else {
			$this -> _code = 60001;
		}

		$this -> _show();
	}

	//update block---------gid/secretcode/block/site
	function updateinfo_block() {
		$gid = $this -> _request['gid'];
		$secretcode = $this -> _request['secretcode'];
		$block = $this -> _request['block'];
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];
		$where = array("conditions" => "gid='$gid' and sgid='$sgid'");
		$ubase = UBASE::findFirst($where);
		if ($ubase) {
			if ($ubase -> secretcode == $secretcode) {
				$ubase -> block = $block;
				$ubase -> updated = time();
				$ubase -> secretcode = md5($ubase -> updated . $ubase -> password);
				if ($ubase -> save()) {
					$uinfos = UINFO::findFirst($where);
					$this -> _output_additional['gid'] = $ubase -> gid;
					$this -> _output_additional['secretcode'] = $ubase -> secretcode;
					$this -> _output_additional['name'] = $uinfos -> name;
					$this -> _output_additional['username'] = $uinfos -> username;
					$this -> _output_additional['block'] = $ubase -> block;
					$this -> _code = 40011;
				} else {
					$this -> _code = 90001;
				}
			} else {
				$this -> _code = 10014;
			}
		} else {
			$this -> _code = 40002;
		}
		$this -> _show();
	}

	//更新密码或忘记密码
	//忘记密码：mobile/password/vcode/site   更新密码：mobile/password/sign/site
	function updateinfo_password() {
		$mobile = $this -> _request['mobile'];
		$sign = $this -> _request['sign'];
		$vcode = $this -> _request['vcode'];
		$password = $this -> _request['password'];
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];

		$where_user = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
		if ($guser = UBASE::findFirst($where_user)) {
			if ($this -> _userInSiteActivedOrNot($guser -> gid, $sid)) {
				$Vcode = Vcode::_checkVcode($mobile, $sid, $vcode, 2);
				if (!$Vcode && $sign == NULL) {
					$this -> _code = 90010;
					$this -> _show();

				}
				if (($sign != md5($mobile . $guser -> secretcode)) && $vcode == NULL) {
					$this -> _code = 40010;
					$this -> _show();
				}
				if ($Vcode) {
					$Vcode -> active = 1;
					$Vcode -> save();
				}
				$guser -> password = md5($password . $mobile);
				$guser -> updated = time();
				$guser -> secretcode = md5($guser -> updated . $guser -> password);
				if ($guser -> save()) {
					$ubaseinfo = UBASE::findFirst($where_user);
					$this -> _output_additional['gid'] = $ubaseinfo -> gid;
					$this -> _output_additional['secretcode'] = $ubaseinfo -> secretcode;
					//根据gid与sgid获取name与username
					$where_info = array("conditions" => "gid='$ubaseinfo->gid' and sgid='$ubaseinfo->sgid'");
					$uinfos = UINFO::findFirst($where_info);
					$this -> _output_additional['name'] = $uinfos -> name;
					$this -> _output_additional['username'] = $uinfos -> username;
					$this -> _code = 40007;
				} else {
					$this -> _code = 90001;
				}
			} else {
				$this -> _code = 60001;
			}

		}
		$this -> _show();
	}

	//no change
	function uploadpicture() {
		//var_dump($this -> _request);
		$time = $this -> _request['time'];
		//$filename = $this -> _request['filename'];
		$sign = $this -> _request['sign'];
		$check = false;
		if (abs(time() - $time) > 60 * 15) {
			$this -> _code = 90013;
			//} else if ($sign != md5($time)) {
			//	$this -> _code = 90014;
		} else if ($this -> request -> hasFiles()) {
			$files = $this -> request -> getUploadedFiles();
			if (count($files) == 1) {
				$file = $files[0];
				$filesize = $file -> getSize();

				if ($filesize == 0 || $filesize > 1024 * 1024 * 5) {
					$this -> _code = 90004;
				} else {
					$dir_date = date("Ymd");
					$dirFile = dirname(__FILE__) . '/../../public/upload/user/' . $dir_date . "/";

					if (!file_exists($dirFile)) {
						mkdir($dirFile, 0777, true);
					}
					$dscFile = $dirFile . $file -> getName();
					$dscFile = pathinfo($dscFile);
					$filename = md5(time() . rand(1, 999999)) . "." . strtolower($dscFile['extension']);
					$dscFile = $dirFile . $filename;
					$file -> moveTo($dscFile);
					$file = fopen($dscFile, "rb");
					$bin = fread($file, 2);
					//只读2字节
					fclose($file);
					$strInfo = @unpack("C2chars", $bin);
					$typeCode = intval($strInfo['chars1'] . $strInfo['chars2']);
					$typeCodes = array(255216, 7173, 6677, 13780);
					if (in_array($typeCode, $typeCodes)) {
						$this -> _output_additional['url'] = "http://cp.hearst.com.cn/upload/user/$dir_date/$filename";
						$this -> _code = 90006;
					} else {
						unset($dscFile);
						$this -> _code = 90005;
					}
				}
			} else {
				$this -> _code = 90002;
			}
		} else {
			$this -> _code = 90012;
		}
		$this -> _show();
	}

	//"gid","secretcode","activityname","type","site",["activitydate","activityurl"]
	function sendmessage() {
		$gid = $this -> _request['gid'];
		$secretcode = $this -> _request['secretcode'];
		$activity_name = ($this -> _request['activityname']);
		$activity_url = ($this -> _request['activityurl']);
		$activity_date = ($this -> _request['activitydate']);

		$type = $this -> _request['type'];
		$site = $this -> _request['site'];
		$sid = $this -> _site[$site];
		$sgid = $this -> _sgid[$this -> _site[$site]];
		$message_template = $this -> _apiconfig -> message_template;
		if ($this -> _userInSiteActivedOrNot($gid, $sid)) {
			if ($activity_name == '') {
				$this -> _code = 50001;
			} else if ($type != 1 && $type != 2 && $type!=7) {
				$this -> _code = 50002;
			} else if ($site != 'ellefit' and $site != 'ellemen') {
				$this -> _code = 50004;
			} else {
				//$where_user = array("conditions" => "secretcode = '$secretcode' and gid='$gid'");
				if ($userinfo = $this -> _checkUser($gid, $secretcode, $sgid)) {

					$message = str_replace('activityname', $activity_name, $message_template[$site . "_" . $type]);
					$message = str_replace('url', $activity_url, $message);
					$weekarray = array("日", "一", "二", "三", "四", "五", "六");
					$activity_date = date("m月d日", strtotime($activity_date)) . "星期" . $weekarray[date("w", strtotime($activity_date))];
					$message = str_replace('date', $activity_date, $message);
					$this -> _sendMessage($this -> _output_additional['mobile'], $message);
					if ($this -> _code == 90007) {
						$data['mobile'] = $this -> _output_additional['mobile'];
						$data['action'] = $type + 4;
						$data['created'] = $data['updated'] = time();
						$data['expires'] = $data['created'];
						$data['sign'] = md5($userinfo -> mobile . $data['expires']);
						$data['vcode'] = 0;
						$data['message'] = $message;
						$data['active'] = 1;
						$data['sid'] = $this -> _site[$site];
						$vcode = new Vcode();
						$rtn = $vcode -> save($data);
					}
				}
			}
		} else {
			$this -> _code = 60001;
		}

		$this -> _show();
	}

	//"mobile","site","activityname","activitydate","activityurl","type"
	function sendmultimessage() {
		$mobile = $this -> _request['mobile'];

		$activity_name = ($this -> _request['activityname']);
		$activity_url = ($this -> _request['activityurl']);
		$activity_date = ($this -> _request['activitydate']);

		$type = $this -> _request['type'];
		$site = $this -> _request['site'];

		$message_template = $this -> _apiconfig -> message_template;
		if ($activity_name == '') {
			$this -> _code = 50001;
		} else if ($type < 1 or $type > 3) {
			$this -> _code = 50002;
		} else if ($site != 'ellefit' and $site != 'ellemen') {
			$this -> _code = 50004;
		} else {
			$sMobile = '';
			$mobile = explode(",", $mobile);
			for ($i = 0; $i < count($mobile); $i++) {
				$mobile[$i] = "86" . $mobile[$i];
			}

			$message = str_replace('activityname', $activity_name, $message_template[$site . "_" . $type]);
			$message = str_replace('url', $activity_url, $message);
			$weekarray = array("日", "一", "二", "三", "四", "五", "六");
			$activity_date = date("m月d日", strtotime($activity_date)) . "星期" . $weekarray[date("w", strtotime($activity_date))];
			$message = str_replace('date', $activity_date, $message);
			$this -> _sendMessage($mobile, $message);
			if ($this -> _code == 90007) {
				$data['mobile'] = $userinfo -> mobile;
				$data['action'] = $type + 4;
				$data['created'] = $data['updated'] = time();
				$data['expires'] = $data['created'];
				$data['sign'] = md5($userinfo -> mobile . $data['expires']);
				$data['vcode'] = 0;
				$data['message'] = $message;

				$data['active'] = 1;
				$data['sid'] = $this -> _site[$site];
				$vcode = new Vcode();
				$rtn = $vcode -> save($data);
			}

		}
		$this -> _show();
	}
	
	//"site","type","orderid",["activityname","orderprice"]
	function sendmanagermessage(){
		$mobile = array("18621729559","18501737750","18302160272","18016399990","18939966322");
		$type = $this -> _request['type'];
		$site = $this -> _request['site'];
		$orderid = $this -> _request['orderid'];
		
		$activity_name = $this -> _request['activityname'];
		$orderprice=$this -> _request['orderprice'];
		$message_template = $this -> _apiconfig -> message_template;
		
		if ($type < 1 or $type > 6) {
			$this -> _code = 50002;
		} else if ($site != 'ellefit' and $site != 'ellemen') {
			$this -> _code = 50004;
		} else {
			for($i=0;$i<count($mobile);$i++){
				$mobile[$i] = "86" . $mobile[$i];
			}
			$message = str_replace('orderid', $orderid, $message_template[$site . "_" . $type]);
			$message = str_replace('activityname',$activity_name,$message);
			$message = str_replace('orderprice',$orderprice,$message);
			$this -> _sendMessage($mobile, $message);
			if ($this -> _code == 90007) {
				$data['mobile'] = $userinfo -> mobile;
				$data['action'] = $type + 4;
				$data['created'] = $data['updated'] = time();
				$data['expires'] = $data['created'];
				$data['sign'] = md5($userinfo -> mobile . $data['expires']);
				$data['vcode'] = 0;
				$data['message'] = $message;

				$data['active'] = 1;
				$data['sid'] = $this -> _site[$site];
				$vcode = new Vcode();
				$rtn = $vcode -> save($data);
			}
		}
		$this->_show();
	}
	//活动验证码校验：mobile,vcode //nochange
	function activity_vcode_active() {
		$user = array();
		$user['mobile'] = $mobile = $this -> _request['mobile'];
		$vcode = $this -> _request['vcode'];
		$where = array("conditions" => "mobile='$mobile' and vcode = '$vcode' and expires>'" . time() . "' and action =3");
		$Vcode = Vcode::findFirst($where);
		if (!$Vcode) {
			$this -> _code = 90010;
		} else {
			if ($Vcode -> active == 0) {
				$Vcode -> updated = time();
				$Vcode -> active = 1;
				$Vcode -> save();
				$this -> _code = 90015;
			} else {
				$this -> _code = 90016;
			}
		}
		$this -> _show();
	}

	function test() {
		$ip = array("112.253.19.164", "222.184.34.20", "219.145.184.33", "61.155.237.44", "125.75.32.116", "117.23.6.30", "58.216.109.98");
		var_dump(in_array($this -> _get_real_ip(), $ip));
		var_dump(base64_decode("MQ=="));
		exit();
	}

	//获取验证码
	function getvcode() {
		$ip = array("112.253.19.164", "222.184.34.20", "219.145.184.33", "61.155.237.44", "125.75.32.116", "117.23.6.30", "58.216.109.98");
		//if (in_array($this->_get_real_ip(), $ip)){exit();}
		$action = $this -> _request['action'];
		//var_dump(( substr($action,2,2)!= '=='));
		//exit();
		if (substr($action, 2, 2) != '==') {die("gun");
		}
		$mobile = $this -> _request['mobile'];
		$site = $this -> _request['site'];
		if (!($action && $mobile && $site)) {exit();
		}

		$sid = $this -> _site[$site];
		$sgid = $this -> _sgid[$sid];
		switch (base64_decode($action)) {
			//注册 mobile/action/site
			//例子：http://cp.hearst.com.cn/api/user/getvcode&mobile=18621729559&site=ellemen&action=MQ==
			case 1 :
				$where_user = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
				if ($ubase = UBASE::findFirst($where_user)) {
					$this -> _code = 10013;
				}
				break;
			//重置密码 mobile/action/site
			case 2 :
				$where_user = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
				if (!($guser = UBASE::findFirst($where_user))) {
					$this -> _code = 10010;
				}
				break;
			//获取随机验证码 mobile/action/site
			case 3 :
				$where_user = array("conditions" => "$mobile='$mobile' and sgid='$sgid'");
				if (!($guser = UBASE::findFirst($where_user))) {
					$this -> _code = 10016;
				}
				break;
			//激活 mobile/action/site
			case 4 :
				$where_user = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
				if (!($guser = UBASE::findFirst($where_user))) {
					$this -> _code = 10015;
				}
				break;

			default :
				$this -> _code = 90002;
				break;
		}
		//var_dump(strlen($mobile),$this->_code,($this -> _code ==0));exit();
		if ($this -> _code) {
			$this -> _show();
		}

		if (strlen($mobile) == 11 && $this -> _code == 0) {
			$code = sprintf("%06d", rand("100000", "999999"));
			$where = array("conditions" => "action =" . base64_decode($action) . " and mobile='$mobile' and (" . time() . "-created)<=600 and active =0 and sid=$sid", "order" => "created desc");
			$vcode = Vcode::findFirst($where);
			if ($vcode) {
				$this -> _code = 90009;
			} else {
				$this -> _sendGetvcodeMess($site, $code, $mobile);
				if ($this -> _code == 90007) {
					$data['mobile'] = $mobile;
					$data['action'] = base64_decode($action);
					$data['created'] = $data['updated'] = time();
					$data['expires'] = $data['created'] + 60 * 10;
					$data['sign'] = md5($mobile . $data['expires']);
					$data['vcode'] = $code;
					$data['message'] = "验证码：" . $code . "，请在10分钟内完成输入";
					$data['active'] = 0;
					$data['sid'] = $sid;

					$vcode = new Vcode();
					$rtn = $vcode -> save($data);
					$this -> _output_additional['expires'] = $data['expires'];
				}
			}
		} else {
			$this -> _code = 40008;
		}
		$this -> _show();
	}

	function getvcodepost() {
		$this -> _request = $this -> request -> post();

		$action = $this -> _request['action'];
		$mobile = $this -> _request['mobile'];
		$site = $this -> _request['site'];

		if (!($action && $mobile && $site)) {exit();
		}

		$sid = $this -> _site[$site];
		$sgid = $this -> _sgid[$sid];
		switch (base64_decode($action)) {
			//注册 mobile/action/site
			//例子：http://cp.hearst.com.cn/api/user/getvcode&mobile=18621729559&site=ellemen&action=MQ==
			case 1 :
				$where_user = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
				if ($ubase = UBASE::findFirst($where_user)) {
					$this -> _code = 10013;
				}
				break;
			//重置密码 mobile/action/site
			case 2 :
				$where_user = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
				if (!($guser = UBASE::findFirst($where_user))) {
					$this -> _code = 10010;
				}
				break;
			//获取随机验证码 mobile/action/site
			case 3 :
				$where_user = array("conditions" => "$mobile='$mobile' and sgid='$sgid'");
				if (!($guser = UBASE::findFirst($where_user))) {
					$this -> _code = 10016;
				}
				break;
			//激活 mobile/action/site
			case 4 :
				$where_user = array("conditions" => "mobile = '$mobile' and sgid='$sgid'");
				if (!($guser = UBASE::findFirst($where_user))) {
					$this -> _code = 10015;
				}
				break;

			default :
				$this -> _code = 90002;
				break;
		}
		//var_dump(strlen($mobile),$this->_code,($this -> _code ==0));exit();
		if ($this -> _code) {
			$this -> _show();
		}

		if (strlen($mobile) == 11 && $this -> _code == 0) {
			$code = sprintf("%06d", rand("100000", "999999"));
			$where = array("conditions" => "action =" . base64_decode($action) . " and mobile='$mobile' and (" . time() . "-created)<=600 and active =0 and sid=$sid", "order" => "created desc");
			$vcode = Vcode::findFirst($where);
			if ($vcode) {
				$this -> _code = 90009;
			} else {
				$this -> _sendGetvcodeMess($site, $code, $mobile);
				if ($this -> _code == 90007) {
					$data['mobile'] = $mobile;
					$data['action'] = base64_decode($action);
					$data['created'] = $data['updated'] = time();
					$data['expires'] = $data['created'] + 60 * 10;
					$data['sign'] = md5($mobile . $data['expires']);
					$data['vcode'] = $code;
					$data['message'] = "验证码：" . $code . "，请在10分钟内完成输入";
					$data['active'] = 0;
					$data['sid'] = $sid;
					$vcode = new Vcode();
					$rtn = $vcode -> save($data);
					$this -> _output_additional['expires'] = $data['expires'];
				}
			}
		} else {
			$this -> _code = 40008;
		}
		$this -> _show();
	}

	//获取用户openid,判断数据库中是否有，即用户是否绑定过微信：site,openid
	function checkopenid() {
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];
		$openid = $this -> _request['openid'];

		//$weixin=file_get_contents("https://api.weixin.qq.com/sns/oauth2/access_token?appid=wx72deee52df7690e0&secret=c1da7c71a4f26a8dfca5ca60bcfd52a8&code=".$code."&grant_type=authorization_code");
		//$jsondecode=json_decode($weixin);
		//$array=get_object_vars($jsondecode);//转换成数组
		//$openid=$array['openid'];
		$where = array("conditions" => "openid='$openid' and sid='$sid'");
		if ($uopenid = UOPENID::findFirst($where)) {
			//has binded
			$this -> _code = 70005;
			$this -> _output_additional['openid'] = $openid;

			$this -> _setOutputData($uopenid -> gid, $sgid);
		} else {
			//hasn't binded
			$this -> _code = 70006;
			$this -> _output_additional['openid'] = $openid;
		}
		$this -> _show();
	}

	//绑定用户微信openid: openid,site,secretcode,gid,nickname
	function bindwechat() {
		$gid = $this -> _request['gid'];
		$secretcode = $this -> _request['secretcode'];
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];
		if ($uinfos = $this -> _checkUser($gid, $secretcode, $sgid)) {
			$openid = $this -> _request['openid'];
			$where_op = array("conditions" => "sid='$sid' and gid='$gid'");
			$temp_openid = UOPENID::findFirst($where_op);
			if ($temp_openid) {
				if ($temp_openid -> openid == "") {
					$t_uopenid = array();
					$t_uopenid['openid'] = $openid;
					if ($temp_openid -> save($t_uopenid)) {
						$this -> _code = 70001;
						$this -> _output_additional['gid'] = $gid;
						$this -> _output_additional['secretcode'] = $secretcode;
					} else {
						$this -> _code = 90001;
					}
				} else {
					$this -> _code = 70005;
				}
			} else {
				$uopenid = new UOPENID();
				$t_uopenid = array();
				$t_uopenid['sid'] = $sid;
				$t_uopenid['openid'] = $openid;
				$t_uopenid['gid'] = $gid;
				$t_uopenid['nickname']=$this->_request['nickname'];
				if ($uopenid -> save($t_uopenid)) {
					$this -> _code = 70001;
					$this -> _output_additional['gid'] = $gid;
					$this -> _output_additional['secretcode'] = $secretcode;
				} else {
					$this -> _code = 90001;
				}
			}

		}
		$this -> _show();

	}

	//解綁用戶微信openid:openid,site,secretcode,gid
	function unbindwechat() {
		$gid = $this -> _request['gid'];
		$secretcode = $this -> _request['secretcode'];
		$sid = $this -> _site[$this -> _request['site']];
		$sgid = $this -> _sgid[$sid];
		if ($uinfos = $this -> _checkUser($gid, $secretcode, $sgid)) {
			$openid = $this -> _request['openid'];
			$where = array("conditions" => "gid='$gid' and sid='$sid'");
			$uopenid = UOPENID::findFirst($where);
			if ($uopenid) {
				if ($uopenid -> openid == $openid) {
					$uopenid -> openid = "";
					$uopenid -> nickname = "";
					if ($uopenid -> save()) {
						$this -> _code = 70002;
						$this -> _output_additional['gid'] = $gid;
						$this -> _output_additional['secretcode'] = $secretcode;
					} else {
						$this -> _code = 90001;
					}
				} else {
					// return "openid not right"
					$this -> _code = 70003;
				}
			} else {
				//hasn't binded
				$this -> _code = 70006;
			}

		}
		$this -> _show();
	}

	//++lisa 2016/09/08接收供应商返回的参数：command,spid,mtmsgid,mtstat,mterrcode
	function receiveMessReport() {
		$command = $this -> _request['command'];
		$spid = $this -> _request['spid'];
		$mtmsgid = $this -> _request['mtmsgid'];
		//$mtmsgids=$this->_request['mtmsgids'];
		$mtstat = $this -> _request['mtstat'];
		$mterrcode = $this -> _request['mterrcode'];
		$dir_date = date("Ymd");
		$dirFile = dirname(__FILE__) . '/../../public/mes_report/' . $dir_date . "/";
		if (!file_exists($dirFile)) {
			mkdir($dirFile, 0777, true);
		}
		$open = fopen($dirFile . $dir_date . ".txt", "a");
		$str = array("command" => $command, "spid" => $spid, "mtmsgid" => $mtmsgid, "mtstat" => $mtstat, "mterrcode" => $mterrcode, "time" => date("Ymdhis"));
		fwrite($open, json_encode($str) . "\n");
		fclose($open);

	}

	//同步c_site
	private function _syn_site_data() {
		$sites = CSITE::find();

		foreach ($sites as $key => $value) {
			$u_site = new UCSITE();
			$u_site -> id = $value -> id;
			$u_site -> pid = $this -> _sgid[$value -> id];
			$u_site -> alias = $value -> alias;
			$u_site -> name = $value -> name;
			$u_site -> url = $value -> url;
			$u_site -> created = $value -> created;
			$u_site -> updated = $value -> updated;
			$u_site -> active_code = 1;
			$u_site -> save();
			//echo $value->alias;
		}
		$group = array("ELLE" => "ELLE女性", "CAD" => "名车志", "MC" => "嘉人", "FEMINA" => "伊周");
		foreach ($group as $k => $v) {
			$u_site = new UCSITE();
			$u_site -> pid = 0;
			$u_site -> alias = $k;
			$u_site -> name = $v;
			$u_site -> url = "";
			$u_site -> created = $u_site -> updated = time();
			$u_site -> save();

		}

	}

	//同步uc_user_basic
	private function _syn_base_info_data() {
		$userinfo_old = Guserinfo::find();
		foreach ($userinfo_old as $value) {
			$u_base = new UBASE();
			$u_info = new UINFO();
			$gid = $u_base -> gid = $u_info -> gid = $value -> gid;
			$sid_arr = explode(',', $value -> sid);
			$u_base -> from_sid = $sid_arr[0];
			$u_base -> sidgroup = $value -> sid;
			$sgid = $u_base -> sgid = $u_info -> sgid = $this -> _sgid[$sid_arr[0]];
			$u_base -> mobile = $value -> mobile;
			$u_base -> password = $value -> password;
			$u_base -> secretcode = $value -> secretcode;
			$u_base -> block = $value -> block;
			$u_base -> created = $u_info -> created = $value -> created;
			$u_base -> updated = $u_info -> updated = $value -> updated;

			$u_info -> nickname = $value -> nickname;
			$u_info -> name = $value -> name;
			$u_info -> username = $value -> username;
			$u_info -> sex = $value -> sex;
			$u_info -> address = $value -> address;
			$u_info -> postcode = $value -> postcode;
			$u_info -> email = $value -> email;
			$u_info -> birthday = $value -> birthday;
			$u_info -> avator = $value -> avator;
			$u_info -> province = $value -> province;
			$u_info -> city = $value -> city;
			$u_info -> district = $value -> district;
			$u_info -> height = $value -> height;
			$u_info -> weight = $value -> weight;

			if (!($u_base -> create() && $u_info -> create())) {
				$this -> _rowRollback($gid, $sgid);
			}

		}
	}

	//同步uc_vcode
	private function _syn_vcode_data() {
		$vcodes = VcodeOLD::find();
		$arr = array("id", "action", "sign", "mobile", "vcode", "expires", "created", "updated", "message", "active", "sid");
		foreach ($vcodes as $value) {
			$vcode = new Vcode();
			foreach ($arr as $v) {
				$vcode -> $v = $value -> $v;
			}
			$vcode -> create();
		}
		//$this->_show();
	}

	//初始化n_uc_gid表
	private function _ini_n_uc_gid() {

		$where_user = array("order" => "gid desc");
		$userinfo_old = Guserinfo::findFirst($where_user);
		$Ucgid = new UCGID();
		$tmpdata['gid'] = $userinfo_old -> gid + 1000;
		$tmpdata['time'] = time();
		$Ucgid -> save($tmpdata);
	}

	//同步 n_uc_site/n_user_base/n_uc_user_info三張表
	private function syn_all() {
		try {
			//生成表结构
			$this -> _exe_create_tables_sql();
			//同步数据
			$this -> _ini_n_uc_gid();
			$this -> _syn_site_data();
			$this -> _syn_base_info_data();
			$this -> _syn_vcode_data();
			//创建触发器
			$this -> _exe_Trigger_sql();
		} catch (Exception $e) {
			print $e -> getMessage();
			exit();
		}
		echo "success~";

	}

	private function _exe_create_tables_sql() {
		$handle = @fopen(__DIR__ . "/../tmp/create_tables.txt", "r");
		$_mysqli = new mysqli("cad-com-cn.c7rp1s7sgxlr.rds.cn-north-1.amazonaws.com.cn", "admin", "Hearst2015", "content_pool");
		if (mysqli_connect_errno()) {
			exit('连接数据库出错');
		}
		if ($handle) {
			$i = 0;
			while (!feof($handle)) {
				$buffer = fgets($handle, 4096);
				if (!$_mysqli -> query($buffer)) {
					$i++;
					exit("sql语句执行失败！" + $i);
				}

			}
			fclose($handle);
		}
		echo "createtables_sql success~";

	}

	//執行外部sql文件
	private function _exe_Trigger_sql() {
		$handle = @fopen(__DIR__ . "/../tmp/uc_trigger.txt", "r");
		$_mysqli = new mysqli("cad-com-cn.c7rp1s7sgxlr.rds.cn-north-1.amazonaws.com.cn", "admin", "Hearst2015", "content_pool");
		if (mysqli_connect_errno()) {
			exit('连接数据库出错');
		}
		if ($handle) {
			while (!feof($handle)) {
				$buffer = fgets($handle, 4096);
				if (!$_mysqli -> query($buffer)) {
					exit("sql语句执行失败！");
				}

			}
			fclose($handle);
		}
		echo "trigger_sql success~";
	}

	private function _truncate_table() {
		$counts = UMobileProvince::count();
		if ($counts != 0) {
			$data = UMobileProvince::find();
			if ($data -> delete()) {
				return TRUE;

			} else {
				return FALSE;
			}
		}
	}

	//同步sql
	private function sys_mobile_province() {

		$page = !empty($this -> _request['page']) ? $this -> _request['page'] : 1;
		$total = !empty($this -> _request['total']) ? $this -> _request['total'] : 1;
		$limit = !empty($this -> _request['limit']) ? $this -> _request['limit'] : 10;
		$total = UBASE::count();
		$where = array("limit" => array("number" => $limit, "offset" => ($page - 1) * $limit), "order" => "gid asc");
		$ubase = UBASE::find($where);
		//$ubase
		foreach ($ubase as $v) {
			$ump = new UMobileProvince();
			$ump -> gid = $v -> gid;
			$ump -> mobile = $mobile = $v -> mobile;
			$ump -> created = $v -> created;
			$ump -> updated = $v -> updated;
			list($ump -> resultcode, $ump -> reason, $ump -> province, $ump -> city, $ump -> areacode, $ump -> zip, $ump -> mobile_company, $ump -> card, $ump -> errcode) = $this -> _get_mobile_province_resluts($mobile);
			if ($ump -> create() == false) {
				foreach ($ump->getMessages() as $message) {
					$myfile = fopen(dirname(__FILE__) . '/../../public/logs_syn/logs_mobile_province.txt', "a");
					fwrite($myfile, "gid" . $v -> gid . ":" . $message -> getMessage() . "\r\n");
					fclose($myfile);
					echo "gid" . $v -> gid . ":" . $message -> getMessage() . "<br>";
				}
			}
		}
		$page = $page + 1;
		$next_url = "/api/user/sys_mobile_province?page=$page&total=$total&limit=$limit";
		$html_body[] = $next_url;
		$html_body[] = "select * from n_mobile_province limit " . ($page - 1) * $limit . "," . $limit;
		if ($page * $limit < $total) {
			$meta = '<meta http-equiv="refresh" content="0;url=' . $next_url . '" >';
		}
		$html_body[] = "end get time:\t" . date("Y-m-d H:i:s", time());
		$html_body[] = "已插入" . $page * $limit . "条数据 insert done!<br>";
		$html_body = implode("<br>", $html_body);
		$html = "<html><head><meta charset='utf-8'/>$meta</head><body>$html_body</body></html>";
		//写入Log
		$myfile = fopen(dirname(__FILE__) . '/../../public/logs_syn/logs_mobile_province.txt', "a");
		fwrite($myfile, $html . "\r\n");
		fclose($myfile);
		echo $html;
	}

}
