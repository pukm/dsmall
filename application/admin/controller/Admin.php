<?php

namespace app\admin\controller;

use think\Lang;
use think\Validate;

class Admin extends AdminControl {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'admin/lang/zh-cn/admin.lang.php');
    }

    /**
     * 管理员列表
     */
    public function admin() {
        $admin_mod=model('admin');
        if (!request()->isPost()) {
            $condition = array(); 
            $admin_list = $admin_mod->getAdminList($condition,10);
            $this->assign('admin_list', $admin_list);
            $this->assign('show_page', $admin_mod->page_info->render());
            $this->setAdminCurItem('admin');
            return $this->fetch('admin');
        } else {
            //ID为1的会员不允许删除
            if (@in_array(1, $_POST['del_id'])) {
                $this->error(lang('admin_index_not_allow_del'));
            }
            if (!empty($_POST['del_id'])) {
                if (is_array($_POST['del_id'])) {
                    foreach ($_POST['del_id'] as $k => $v) {
                        $admin_mod->delAdmin(array('admin_id' => intval($v)));
                    }
                }
                $this->log(lang('ds_del').lang('limit_admin'), 1);
                $this->error(lang('ds_common_del_succ'));
            } else {
                $this->error(lang('ds_common_del_succ'));
            }
        }
    }

    /**
     * 管理员删除
     */
    public function admin_del() {
        $admin_id = intval(input('param.admin_id'));
        if (!empty($admin_id)) {
            if ($admin_id == 1) {
                $this->error(lang('ds_common_save_fail'));
            }
            $admin_mod=model('admin');
            $admin_mod->delAdmin(array('admin_id' => $admin_id));
            $this->log(lang('ds_del').lang('limit_admin') . '[ID:' . $admin_id . ']', 1);
            ds_json_encode(10000, lang('ds_common_del_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_del_fail'));
        }
    }

    /**
     * 管理员添加
     */
    public function admin_add() {
        $admin_model = model('admin');
        if (!request()->isPost()) {
            //得到权限组
            $gadmin = $admin_model->getGadminList('gname,gid');
            $this->assign('gadmin', $gadmin);
            return $this->fetch('admin_add');
        } else {
            $data['admin_name'] = input('post.admin_name');
            $data['admin_gid'] = input('post.gid');
            $data['admin_password'] = md5(input('post.admin_password'));
            
            //验证数据  BEGIN
            $rule = [
                ['admin_name', 'require|length:3,12|unique:admin', '登录名必填|登录名长度在3到12位|登录名已存在'],
                ['admin_password', 'require', '密码为必填'],
                ['admin_gid', 'require', '权限组为必填']
            ];
            $validate = new Validate($rule);
            $validate_result = $validate->check($data);
            if (!$validate_result) {
                $this->error($validate->getError());
            }
            
            $rs = $admin_model->addAdmin($data);
            if ($rs) {
                $this->log(lang('ds_add').lang('limit_admin') . '[' . input('post.admin_name') . ']', 1);
                dsLayerOpenSuccess(lang('ds_common_save_succ'));
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    /**
     * ajax操作
     */
    public function ajax() {
        $admin_model = model('admin');
        switch (input('get.branch')) {
            //管理人员名称验证
            case 'check_admin_name':
                $condition['admin_name'] = input('get.admin_name');
                $admin_info = $admin_model->infoAdmin($condition);
                if (!empty($admin_info)) {
                    exit('false');
                } else {
                    exit('true');
                }
                break;
            //权限组名称验证
            case 'check_gadmin_name':
                $condition = array();
                if (is_numeric(input('param.gid'))) {
                    $condition['gid'] = array('neq', intval(input('param.gid')));
                }
                $condition['gname'] = input('get.gname');
                $info = $admin_model->getOneGadmin($condition);
                if (!empty($info)) {
                    exit('false');
                } else {
                    exit('true');
                }
                break;
        }
    }

    /**
     * 设置管理员权限
     */
    public function admin_edit() {
        $admin_id = intval(input('param.admin_id'));
        if (request()->isPost()) {
            //没有更改密码
            if (input('post.new_pw') != '') {
                $data['admin_password'] = md5(input('post.new_pw'));
            }
            $data['admin_gid'] = intval(input('post.gid'));
            //查询管理员信息
            $admin_model = model('admin');
            $result = $admin_model->editAdmin($data,$admin_id);
            if ($result) {
                $this->log(lang('ds_edit').lang('limit_admin') . '[ID:' . $admin_id . ']', 1);
                dsLayerOpenSuccess(lang('admin_edit_success'));
            } else {
                $this->error(lang('admin_edit_fail'));
            }
        } else {
            //查询用户信息
            $admin_model = model('admin');
            $admin = $admin_model->getOneAdmin(array('admin_id'=>$admin_id));
            if (!is_array($admin) || count($admin) <= 0) {
                $this->error(lang('admin_edit_admin_error'), url('Admin/admin'));
            }
            $this->assign('admin', $admin);
            
            //得到权限组
            $gadmin = $admin_model->getGadminList('gname,gid');
            $this->assign('gadmin', $gadmin);
            return $this->fetch('admin_edit');
        }
    }

    /**
     * 取得所有权限项
     *
     * @return array
     */
    private function permission() {
        $limit = $this->limitList();
        if (is_array($limit)) {
            foreach ($limit as $k => $v) {
                if (is_array($v['child'])) {
                    $tmp = array();
                    foreach ($v['child'] as $key => $value) {
                        $controller = (!empty($value['controller'])) ? $value['controller'] : $v['controller'];
                        if (strpos($controller, '|') == false) {//controller参数不带|
                            $limit[$k]['child'][$key]['action'] = rtrim($controller . '.' . str_replace('|', '|' . $controller . '.', $value['action']), '.');
                        } else {//controller参数带|
                            $tmp_str = '';
                            if (empty($value['action'])) {
                                $limit[$k]['child'][$key]['action'] = $controller;
                            } elseif (strpos($value['action'], '|') == false) {//action参数不带|
                                foreach (explode('|', $controller) as $v1) {
                                    $tmp_str .= "$v1.{$value['action']}|";
                                }
                                $limit[$k]['child'][$key]['action'] = rtrim($tmp_str, '|');
                            } elseif (strpos($value['action'], '|') != false && strpos($controller, '|') != false) {//action,controller都带|，交差权限
                                foreach (explode('|', $controller) as $v1) {
                                    foreach (explode('|', $value['action']) as $v2) {
                                        $tmp_str .= "$v1.$v2|";
                                    }
                                }
                                $limit[$k]['child'][$key]['action'] = rtrim($tmp_str, '|');
                            }
                        }
                    }
                }
            }
            return $limit;
        } else {
            return array();
        }
    }

    /**
     * 权限组
     */
    public function gadmin() {
        $admin_model = model('admin');
        if (!request()->isPost()) {
            $gadmin_list = $admin_model->getGadminList();
            $this->assign('gadmin_list', $gadmin_list);
            $this->setAdminCurItem('gadmin');
            return $this->fetch('gadmin');
        } else {
            if (@in_array(1, $_POST['del_id'])) {
                $this->error(lang('admin_index_not_allow_del'));
            }
            if (!empty($_POST['del_id'])) {
                if (is_array($_POST['del_id'])) {
                    foreach ($_POST['del_id'] as $k => $v) {
                        $admin_model->delGadmin(array('gid' => intval($v)));
                    }
                }
                $this->log(lang('ds_del').lang('limit_gadmin') . '[ID:' . implode(',', $_POST['del_id']) . ']', 1);
                $this->success(lang('ds_common_del_succ'));
            } else {
                $this->error(lang('ds_common_del_fail'));
            }
        }
    }

    /**
     * 添加权限组
     */
    public function gadmin_add() {
        if (!request()->isPost()) {
            $this->assign('limit', $this->permission());
            return $this->fetch('gadmin_add');
        } else {
            $limit_str = '';
            if (is_array($_POST['permission'])) {
                $limit_str = implode('|', $_POST['permission']);
            }
            $data['glimits'] = ds_encrypt($limit_str, MD5_KEY . md5(input('post.gname')));
            $data['gname'] = input('post.gname');
            $admin_model = model('admin');
            if ($admin_model->addGadmin($data)) {
                $this->log(lang('ds_add').lang('limit_gadmin') . '[' . input('post.gname') . ']', 1);
                dsLayerOpenSuccess(lang('ds_common_save_succ'));
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    /**
     * 设置权限组权限
     */
    public function gadmin_set() {
        $gid = intval(input('param.gid'));
        $admin_model = model('admin');
        $ginfo = $admin_model->getOneGadmin(array('gid'=>$gid));
        if (empty($ginfo)) {
            $this->error(lang('admin_set_admin_not_exists'));
        }
        if (!request()->isPost()) {
            //解析已有权限
            $hlimit = ds_decrypt($ginfo['glimits'], MD5_KEY . md5($ginfo['gname']));
            $ginfo['glimits'] = explode('|', $hlimit);
            $this->assign('ginfo', $ginfo);
            $this->assign('limit', $this->permission());
            return $this->fetch('gadmin_set');
        } else {
            $limit_str = '';
            if (is_array($_POST['permission'])) {
                $limit_str = implode('|', $_POST['permission']);
            }
            $limit_str = ds_encrypt($limit_str, MD5_KEY . md5(input('post.gname')));
            $data['glimits'] = $limit_str;
            $data['gname'] = input('post.gname');
            $update = $admin_model->editGadmin(array('gid' => $gid),$data);
            if ($update) {
                $this->log(lang('ds_edit').lang('limit_gadmin') . '[' . input('post.gname') . ']', 1);
                dsLayerOpenSuccess(lang('ds_common_save_succ'));
            } else {
                $this->error(lang('ds_common_save_succ'));
            }
        }
    }

    /**
     * 组删除
     */
    public function gadmin_del() {
        if (is_numeric(input('param.gid'))) {
            $admin_model = model('admin');
            $admin_model->delGadmin(array('gid' => intval(input('param.gid'))));
            $this->log(lang('ds_del').lang('limit_gadmin') . '[ID' . intval(input('param.gid')) . ']', 1);
            ds_json_encode(10000, lang('ds_common_op_succ'));
        } else {
            ds_json_encode(10000, lang('ds_common_op_fail'));
        }
    }

    /**
     * 获取卖家栏目列表,针对控制器下的栏目
     */
    protected function getAdminItemList() {
        $menu_array = array(
            array(
                'name' => 'admin',
                'text' => '管理员',
                'url' => url('Admin/admin')
            ),
            array(
                'name' => 'admin_add',
                'text' => '添加管理员',
                'url' => "javascript:dsLayerOpen('".url('Admin/admin_add')."','添加管理员')"
            ),
            array(
                'name' => 'gadmin',
                'text' => '权限组',
                'url' => url('Admin/gadmin')
            ),
            array(
                'name' => 'gadmin_add',
                'text' => '添加权限组',
                'url' => "javascript:dsLayerOpen('".url('Admin/gadmin_add')."','添加权限组')"
            ),
        );
        return $menu_array;
    }

}

?>
