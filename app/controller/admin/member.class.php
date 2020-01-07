<?php
/*
* @link http://kodcloud.com/
* @author warlee | e-mail:kodcloud@qq.com
* @copyright warlee 2014.(Shanghai)Co.,Ltd
* @license http://kodcloud.com/tools/license/license.txt
*/

//用户管理【管理员配置用户，or用户空间大小变更】
class adminMember extends Controller{
	private $model;
	function __construct()    {
		parent::__construct();
		$this->model = Model('User');
	}

	/**
	 * 根据所在部门获取用户列表
	 */
	public function get() {
		$rootGroupID = 1;
		$id = Input::get('groupID','int',$rootGroupID);
		$id = $id == 1 ? 0 : $id;	// 根部门（id=1）获取全部用户
		$result = $this->model->listByGroup($id);
		show_json($result,true);
	}
	
	/**
	 * 根据用户id获取信息
	 */
	public function getByID() {
		$id = Input::get('id','[\d,]*');
		$result = $this->model->listByID(explode(',',$id));
		show_json($result,true);
	}

	/**
	 * 搜索用户
	 */
	public function search() {
		$data = Input::getArray(array(
			"words" 		=> array("check"=>"require"),
			"parentGroup"	=> array("check"=>"int",'default'=>false),
		));
		$result = $this->model->listSearch($data['words'],$data['parentGroup']);
		show_json($result,true);
	}
	
	/**
	 * 添加用户
	 */
	public function add() {
		$data = Input::getArray(array(
			"name" 		=> array("check"=>"require"),
			"sizeMax" 	=> array("check"=>"float","default"=>1024*1024*100),
			"roleID"	=> array("check"=>"int"),
			"password" 	=> array("check"=>"require"),
			
			"email" 	=> array("check"=>"email",	"default"=>""),
			"phone" 	=> array("check"=>"phone",	"default"=>""),
			"nickName" 	=> array("check"=>"require","default"=>""),
			"avatar" 	=> array("check"=>"require","default"=>""),
			"sex" 		=> array("check"=>"require","default"=>""),//0女1男
			"status" 	=> array("default"=>1),
		));
		// 1.添加用户
		$res = $userID = $this->model->userAdd($data);
		if($res <= 0) return show_json($this->model->errorLang($res),false);

		$groupInfo = json_decode($this->in['groupInfo'],true);
		if(is_array($groupInfo)){
			$this->model->userGroupSet($userID,$groupInfo,true);
		}

		// 2.添加用户默认配置
		$userInfo = $this->model->getInfo($userID);
		$this->settingDefault($userID);

		// 3.添加用户默认目录
		$sourceID = $userInfo['sourceInfo']['sourceID'];
		$this->folderDefault($sourceID);

		// 4.添加用户默认轻应用
		$desktopID = $userInfo['sourceInfo']['desktop'];
		$this->lightAppDefault($desktopID);
		return show_json(LNG('explorer.success'), true, $userID);
	}

	/**
     * 用户默认设置——主题、壁纸、界面样式选择等
     */
    public function settingDefault($userID){
		$default = $this->config['settingDefault'];
        $insert = array();
        foreach($default as $key => $value){
            $insert[] = array(
				'type'		=> '',
                'userID'	=> $userID,
				'key'		=> $key,
				'value'		=> $value
            );
        }
        Model('user_option')->addAll($insert);
	}

    /**
     * 用户默认目录
     */
    public function folderDefault($parentID){
        $folderDefault = Model('SystemOption')->get('newUserFolder');
		$folderList = explode(',', $folderDefault);
        foreach($folderList as $name){
            $path = "{source:{$parentID}}/" . $name;
            IO::mkdir($path);
        }
	}
	
	/**
     * 添加用户轻应用
     */
    public function lightAppDefault($desktop){
        $list = Model('SystemLightApp')->listData();
        $appList = array_to_keyvalue($list, 'name');

        $defaultApp = Model('SystemOption')->get('newUserApp');
		$defAppList = explode(',', $defaultApp);
        foreach($defAppList as $name){
            if(!isset($appList[$name])) continue;
			$app = $appList[$name];
			// [user]/desktop/appName.oexe
            $path = "{source:{$desktop}}/" . $app['name'] . '.oexe';
            IO::mkfile($path, json_encode_force($app['content']));
        }
    }

	/**
	 * 编辑 
	 */
	public function edit() {
		$data = Input::getArray(array(
			// "userID" 	=> array("check"=>"bigger",'param'=>1),
			"userID" 	=> array("check"=>"int"),	// userID=1可以编辑
			
			"name" 		=> array("check"=>"require","default"=>null),
			"sizeMax" 	=> array("check"=>"float",	"default"=>null),
			"roleID"	=> array("check"=>"int",	"default"=>null),
			"password" 	=> array("check"=>"require","default"=>''),
			
			"email" 	=> array("check"=>"email",	"default"=>''),
			"phone" 	=> array("check"=>"phone",	"default"=>''),
			"nickName" 	=> array("check"=>"require","default"=>null),
			"avatar" 	=> array("check"=>"require","default"=>''),
			"sex" 		=> array("check"=>"require","default"=>null),//0女1男
			
			"status" 	=> array("check"=>"require","default"=>null),//0-未启用 1-启用
		));

		$res = $this->model->userEdit($data['userID'],$data);
		$groupInfo = json_decode($this->in['groupInfo'],true);
		if($res && is_array($groupInfo)){
			$this->model->userGroupSet($data['userID'],$groupInfo,true);
		}
		$msg = $res > 0 ? LNG('explorer.success') : $this->model->errorLang($res);
		return show_json($msg,($res>0),$data['userID']);
	}
	
	public function addGroup() {
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),
			"groupID"	=> array("check"=>"int"),
			"authID"	=> array("check"=>"int"),
		));
		$res = $this->model->userGroupAdd($data['userID'],$data['groupID'],$data['authID']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	public function removeGroup() {
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),
			"groupID"	=> array("check"=>"int"),
		));
		$res = $this->model->userGroupRemove($data['userID'],$data['groupID']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}

	public function status(){
		$data = Input::getArray(array(
			"userID" 	=> array("check"=>"int"),
			"status"	=> array("check"=>"in", "param" => array(0, 1)),
		));
		$res = $this->model->userStatus($data['userID'], $data['status']);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
	
	/**
	 * 删除
	 */
	public function remove() {
		$id = Input::get('userID','bigger',null,1);
		$res = $this->model->userRemove($id);
		$msg = $res ? LNG('explorer.success') : LNG('explorer.error');
		show_json($msg,!!$res);
	}
}
