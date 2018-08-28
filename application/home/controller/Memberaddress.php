<?php

namespace app\home\controller;

use think\Lang;
use think\Validate;

class Memberaddress extends BaseMember {

    public function _initialize() {
        parent::_initialize();
        Lang::load(APP_PATH . 'home/lang/zh-cn/memberaddress.lang.php');
    }

    /*
     * 收货地址列表
     */

    public function index() {
        $address_model=model('address');
        $address_list = $address_model->getAddressList(array('member_id'=>session('member_id')));
        $this->assign('address_list', $address_list);

        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('member_address');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('my_address');
        return $this->fetch($this->template_dir . 'index');
    }

    public function add() {
        if (!request()->isPost()) {
            $area_mod=model('area');
            $region_list = $area_mod->getAreaList(array('area_parent_id'=>'0'));
            $this->assign('region_list', $region_list);
            $address = array(
                'address_realname' => '',
                'area_id' => '',
                'city_id' => '',
                'address_detail' => '',
                'address_tel_phone' => '',
                'address_mob_phone' => '',
                'address_is_default' => '',
                'area_info' => '',
                'address_longitude' => '',
                'address_latitude' => '',
            );
            $this->assign('address', $address);
            /* 设置买家当前菜单 */
            $this->setMemberCurMenu('member_address');
            /* 设置买家当前栏目 */
            $this->setMemberCurItem('my_address_add');
            $this->assign('baidu_ak', config('baidu_ak'));
            return $this->fetch($this->template_dir . 'form');
        } else {
            $data = array(
                'member_id' => session('member_id'),
                'address_realname' => input('post.true_name'),
                'area_id' => input('post.area_id'),
                'city_id' => input('post.city_id'),
                'address_detail' => input('post.address'),
                'address_longitude' => input('post.longitude'),
                'address_latitude' => input('post.latitude'),
                'address_tel_phone' => input('post.tel_phone'),
                'address_mob_phone' => input('post.mob_phone'),
                'address_is_default' => input('post.is_default') == 1 ? 1 : 0,
                'area_info' => input('post.area_info'),
            );
            //验证数据  BEGIN
            $rule = [
                ['address_realname', 'require', '真实姓名必填'],
                ['city_id', 'gt:1', '请选择地区'],
                ['area_id', 'gt:1', '地区至少两级'],
            ];
            $validate = new Validate();
            $validate_result = $validate->check($data, $rule);
            if (!$validate_result) {
                $this->error($validate->getError());
            }
            //验证数据  END
            $address_model=model('address');
            $result = $address_model->addAddress($data);
            if ($result) {
                $this->success(lang('ds_common_save_succ'), 'Memberaddress/index');
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    public function edit() {

        $address_id = intval(input('param.address_id'));
        if (0 >= $address_id) {
            $this->error('参数错误');
        }
        $address_model=model('address');
        $address = $address_model->getAddressInfo(array('member_id' => session('member_id'), 'address_id' => $address_id));
        if (empty($address)) {
            $this->error('地址不存在');
        }
        if (!request()->isPost()) {
            $area_mod=model('area');
            $region_list = $area_mod->getAreaList(array('area_parent_id'=>'0'));
            $this->assign('region_list', $region_list);
            $this->assign('address', $address);
            /* 设置买家当前菜单 */
            $this->setMemberCurMenu('member_address');
            /* 设置买家当前栏目 */
            $this->setMemberCurItem('my_address_edit');
            $this->assign('baidu_ak', config('baidu_ak'));
            return $this->fetch($this->template_dir . 'form');
        } else {
            $data = array(
                'address_realname' => input('post.true_name'),
                'area_id' => input('post.area_id'),
                'city_id' => input('post.city_id'),
                'address_detail' => input('post.address'),
                'address_longitude' => input('post.longitude'),
                'address_latitude' => input('post.latitude'),
                'address_tel_phone' => input('post.tel_phone'),
                'address_mob_phone' => input('post.mob_phone'),
                'address_is_default' => input('post.is_default') == 1 ? 1 : 0,
                'area_info' => input('post.area_info'),
            );
            //验证数据  BEGIN
            $rule = [
                ['address_realname', 'require', '真实姓名必填'],
                ['city_id', 'gt:1', '请选择地区'],
                ['area_id', 'gt:1', '地区至少两级'],
            ];
            $validate = new Validate();
            $validate_result = $validate->check($data, $rule);
            if (!$validate_result) {
                $this->error($validate->getError());
            }
            //验证数据  END

            $result = $address_model->editAddress($data,array('member_id' => session('member_id'), 'address_id' => $address_id));
            if ($result) {
                $this->success(lang('ds_common_save_succ'), 'Memberaddress/index');
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    public function drop() {
        $address_id = intval(input('param.address_id'));
        if (0 >= $address_id) {
            $this->error(lang('empty_error'));
        }
        $address_model=model('address');
        $result = $address_model->delAddress(array('address_id'=>$address_id));
        if ($result) {
            $this->success(lang('ds_common_del_succ'), 'Memberaddress/index');
        } else {
            $this->error(lang('ds_common_del_fail'));
        }
    }

    /**
     * 添加自提点型收货地址
     */
    public function delivery_add() {
        if (request()->isPost()) {
            $info = model('deliverypoint')->getDeliverypointOpenInfo(array('dlyp_id' => intval(input('param.dlyp_id'))));
            if (empty($info)) {
                ds_show_dialog('该自提点不存在', '', 'error');
            }
            $data = array();
            $data['member_id'] = session('member_id');
            $data['address_realname'] = input('param.true_name');
            $data['area_id'] = $info['dlyp_area_3'];
            $data['city_id'] = $info['dlyp_area_2'];
            $data['area_info'] = $info['dlyp_area_info'];
            $data['address_detail'] = $info['dlyp_address'];
            $data['address_tel_phone'] = input('param.tel_phone');
            $data['address_mob_phone'] = input('param.mob_phone');
            $data['dlyp_id'] = $info['dlyp_id'];
            $data['address_is_default'] = 0;
            if (intval(input('param.address_id'))) {
                $result = model('address')->editAddress($data, array('address_id' => intval(input('param.address_id'))));
            } else {
                $count = model('address')->getAddressCount(array('member_id' => session('member_id')));
                if ($count >= 20) {
                    ds_show_dialog('最多允许添加20个有效地址', '', 'error');
                }
                $result = model('address')->addAddress($data);
            }
            if (!$result) {
                ds_show_dialog('保存失败', '', 'error');
            }
            ds_show_dialog('保存成功', url('Memberaddress/index'), 'js');
        } else {
            if (intval(input('param.address_id')) > 0) {
                $address_model = model('address');
                $condition = array('address_id' => intval(input('param.address_id')), 'member_id' => session('member_id'));
                $address_info = $address_model->getAddressInfo($condition);
                //取出省级ID
                $area_info = model('area')->getAreaInfo(array('area_id' => $address_info['city_id']));
                $address_info['province_id'] = $area_info['area_parent_id'];
                $this->assign('address_info', $address_info);
            }
            /* 设置买家当前菜单 */
            $this->setMemberCurMenu('member_address');
            /* 设置买家当前栏目 */
            $this->setMemberCurItem('delivery_add');
            return $this->fetch($this->template_dir . 'delivery_add');
        }
    }

    /**
     * 展示自提点列表
     */
    public function delivery_list() {
        $deliverypoint_model = model('deliverypoint');
        $condition = array();
        $condition['dlyp_area_3'] = intval(input('param.area_id'));
        $deliverypoint_list = $deliverypoint_model->getDeliverypointOpenList($condition, 5);
        $this->assign('show_page', $deliverypoint_model->page_info->render());
        $this->assign('deliverypoint_list', $deliverypoint_list);
        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('member_address');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('delivery_list');
        echo $this->fetch($this->template_dir . 'delivery_list');exit;
    }

    /**
     *    栏目菜单
     */
    function getMemberItemList() {
        $item_list = array(
            array(
                'name' => 'my_address',
                'text' => '我的地址',
                'url' => url('Memberaddress/index'),
            ),
            array(
                'name' => 'my_address_add',
                'text' => '新增地址',
                'url' => url('Memberaddress/add'),
            ),
        );
        if (request()->action() == 'edit') {
            $item_list[] = array(
                'name' => 'my_address_edit',
                'text' => '编辑地址',
                'url' => "javascript:void(0)",
            );
        }
        if (request()->action() == 'delivery_add') {
            $item_list[] = array(
                'name' => 'delivery_add',
                'text' => '自提设置',
                'url' => "javascript:void(0)",
            );
        }
        return $item_list;
    }

}

?>