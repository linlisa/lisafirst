<?php 
	$api=array(
		"msg"=>array(
			90001=>"系统错误",
			90002=>"参数错误",
			90003=>"接口名不存在",
			90004=>"系统上传文件最大只允许5M",
			90005=>"系统上传文件格式不合法，只允许JPG/PNG/GIF",
			90006=>"文件上传成功",
			90007=>"短信发送成功",
			90008=>"短信发送失败",
			90009=>"无法获取短信验证码，请在10分钟后再行尝试",
			90010=>"验证码错误/验证码已被使用/验证码过期",
			90011=>"头像路径非法",
			90012=>'未上传文件',
			90013=>'签名时间与服务器时间相差10分钟以上，提交失败',
			90014=>'接口签名错误，提交失败',
			
			
			10001=>"账号密码正确/账号验证码正确",
			10002=>"账号/密码错误",
			10003=>'账号已被阻止访问，请联系管理员',
			10010=>"手机号不存在，无法执行重置密码操作",
			10011=>"邮箱不存在",
			10012=>"用户名不存在",
			10013=>"手机号已存在，无法注册",//1
			10014=>"用户安全码错误",
			
			20001=>"注册成功",
			20002=>"手机号已存在",//1
			
			
			30001=>"手机验证码错误",
			30002=>"手机验证码已失效，请重新获取。",
			30003=>"手机号不符合规范",
			40001=>"获取用户信息成功",
			40002=>"用户不存在，信息获取失败",
			40003=>"基础信息更新成功",
			40004=>"手机号更新成功",
			40005=>"邮箱更新成功",
			40006=>"头像更新成功",
			40007=>"密码修改成功",
			40008=>"手机格式不符合要求",
			40009=>"手机号不存在",
			40010=>'密码修改签名错误', 
			40011=>'用户拉黑/拉回操作成功',
			
			50001=>'活动名称不能为空',
			50002=>'模板编码错误',
			50003=>'用户信息不符',
			50004=>'站点名称错误',
			
			//------------//
			60001=>'该用户需要在此站点先激活',
			10015=>'手机号不存在，无法执行激活操作',
			10016=>'手机号不存在，无法获取随即验证码',
			60002=>'激活成功',
			10021=>"密码错误",
			10022=>"账号不存在",
			90015=>"验证码正确",
			90016=>"验证码失效，请重新获取",
			90020=>"系统错误，数据库回滚成功",
			90021=>"系统错误，数据库回滚错误",
			//+2016/08/25 lisa
			70001=>"微信绑定成功",
			70002=>"微信解绑成功",
			70003=>"openid错误",//测试账号
			70005=>"该用户已绑定微信",//该用户openid已经存在数据库中，说明之前绑定过
			70006=>"该用户未绑定微信"
		),
		'param'=>array(
			"login"=>array(
				"required"=>array("mobile","password","site"),
				"optional"=>array("email","username"),
			),
			"register"=>array(
				"required"=>array("password","mobile","site","vcode"),
				"optional"=>array("email","username"),
			),
			"changemobile"=>array(
				"required"=>array("password","mobile","vcode")
			),
			"getinfo"=>array(
				"required"=>array("gid","secretcode","site")
			),
			"updateinfo_base"=>array(
				"required"=>array("gid","secretcode","site","postcode","address","sex","nickname","name","block","height","weight",'birthday')
			),
			"updateinfo_password"=>array(
				"required"=>array("password","mobile","site")
			),
			"updateinfo_mobile"=>array(
				"required"=>array("oldmobile","newmobile","oldvcode","newvcode")
			),
			"updateinfo_avator"=>array(
				"required"=>array("gid","secretcode","avator","site")
			),
			"getvcode"=>array(
				"required"=>array("mobile","action","site")
			),
			"sendmessage"=>array(
				"required"=>array("gid","secretcode","activityname","type","site")
			),
			"sendmultimessage"=>array(
				"required"=>array("mobile","activityname","type","site")
			),
			"updateinfo_block"=>array(
				"required"=>array("gid","secretcode","block","site")
			),
			"activity_vcode_active"=>array(
				"required"=>array("mobile","vcode")
			), 
			"getvcodepost"=>array(),
			"uploadpicture"=>array(),
			"receiveMessReport"=>array(),
			"test"=>array(),
			//+2016/08/25
			"unbindwechat"=>array(
				"required"=>array("openid","site","secretcode","gid")
			),
			"bindwechat"=>array(
				"required"=>array("openid","site","secretcode","gid","nickname")
			),
			"checkopenid"=>array(
				"required"=>array("site","openid")
			),
			"sys_mobile_province"=>array(),
			"sendmanagermessage"=>array(
				"required"=>array("site","type","orderid")
			),
		),
		'message_template'=>array(
			'ellemen_1'=>'你参与的【activityname】活动已经报名成功啦，点这里查看详细信息喔 http://m.ellemen.com/test/activity/#/login',
			'ellemen_2'=>'很抱歉，你报名【activityname】活动未能成功:( 不过睿士俱乐部还是继续欢迎你喔！',
			'ellemen_3'=>'',
			
			'ellefit_1'=>'恭喜你参与date的【activityname】活动成功报名,点这里查看详细信息： url',
			'ellefit_2'=>'抱歉你参与date的【activityname】活动未成功报名,点这里查看详细信息： url',
			'ellefit_3'=>'感谢你参与ELLEfit date 的【activityname】活动，请准时到场，点这里查看注意事项：url',
			'ellefit_4'=>'你有新的订单，订单编号 orderid，活动【activityname】，买家已成功支付。',//ellefit订单支付成功
			'ellefit_5'=>'有买家发起退款，订单编号orderid，退款金额orderprice元，请您尽快登录ELLEfit后台处理。',//ellefit用户退单
			'ellefit_6'=>'订单编号orderid，退款金额orderprice元，微信商户通退款申请已成功处理。',//ellefit退单成功
			'ellefit_7'=>'恭喜你已成功报名date的活动【activityname】，点击链接url 查看二维码，活动当天凭二维码签到入场，请妥善保管本信息。',//ellefit退单成功
			
			
		)
	);
?>