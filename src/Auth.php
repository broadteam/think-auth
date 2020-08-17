<?php
namespace broadteam\think;

use think\facade\Config;
use think\facade\Db;
use think\facade\Request;
use think\facade\Session;

/**
 * 权限认证类
 * 功能特性：
 * 1，是对规则进行认证，不是对节点进行认证。用户可以把节点当作规则名称实现对节点进行认证。
 *   $auth = new Auth();
 *   $auth->check('规则名称', '用户id');
 * 2，可以同时对多条规则进行认证，并设置多条规则的关系（or或者and）
 *   $auth = new Auth();
 *   $auth->check('规则1,规则2', '用户id', 'and');
 *   第三个参数为and时表示，用户需要同时具有规则1和规则2的权限。 当第三个参数为or时，表示用户值需要具备其中一个条件即可。默认为or
 * 3，一个用户可以属于多个用户组(wis_auth_group_access表 定义了用户所属用户组)。我们需要设置每个用户组拥有哪些规则(wis_auth_group 定义了用户组权限)
 * 4，支持规则表达式。
 *   在 wis_auth_rule 表中定义一条规则时，如果type为1， condition字段就可以定义规则表达式。 如定义{score}>5  and {score}<100  表示用户的分数在5-100之间时这条规则才会通过。
 */
// 数据库
/*
CREATE TABLE `tp_administrator` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `fullname` varchar(32) NOT NULL DEFAULT '' COMMENT '昵称/全名',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `password` varchar(64) NOT NULL DEFAULT '' COMMENT '用户密码',
  `salt` varchar(64) NOT NULL DEFAULT '' COMMENT '密码盐值',
  `sex` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '性别',
  `birthday` date NOT NULL DEFAULT '1949-10-01' COMMENT '生日',
  `avatar` varchar(255) NOT NULL DEFAULT '0' COMMENT '用户头像',
  `signature` text NOT NULL COMMENT '用户签名',
  `score` double NOT NULL DEFAULT '0' COMMENT '用户积分',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '用户余额',
  `login_ip` int unsigned NOT NULL DEFAULT '0' COMMENT '登录ip',
  `login_time` int unsigned NOT NULL DEFAULT '0' COMMENT '登录时间',
  `last_login_ip` int unsigned NOT NULL DEFAULT '0' COMMENT '上次登录ip',
  `last_login_time` int unsigned NOT NULL DEFAULT '0' COMMENT '上次登登录时间',
  `login_times` int unsigned NOT NULL DEFAULT '0' COMMENT '登录次数',
  `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '-1删除,0禁用,1可用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='管理员表';

CREATE TABLE `tp_auth_rule_cate` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `pid` int unsigned NOT NULL DEFAULT '0' COMMENT '分类父ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '分类标识',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
  `icon` varchar(50) NOT NULL DEFAULT '' COMMENT '图标',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `sort` smallint NOT NULL DEFAULT '0' COMMENT '排序号',
  `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（0禁用,1可用）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='规则菜单分类表';

CREATE TABLE `tp_auth_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '规则ID',
  `pid` int unsigned NOT NULL DEFAULT '0' COMMENT '规则父ID',
  `type` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '规则类型（0为权限节点，1为菜单，2为按钮）',
  `cate_id` int unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '规则标识',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '规则名称',
  `level` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '层级',
  `condition` varchar(255) NOT NULL DEFAULT '' COMMENT '规则条件（结合用户表其中的字段联合验证）',
  `icon` varchar(50) NOT NULL DEFAULT '' COMMENT '图标',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `sort` smallint NOT NULL DEFAULT '0' COMMENT '排序号',
  `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（0禁用,1可用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`) USING BTREE,
  KEY `pid` (`pid`)
) ENGINE=InnoDB COMMENT='角色规则节点表';

CREATE TABLE `tp_auth_group` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '角色组ID',
  `pid` int unsigned NOT NULL DEFAULT '0' COMMENT '父组ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '组名',
  `rules` text NOT NULL COMMENT '规则IDS',
  `sort` smallint NOT NULL DEFAULT '0' COMMENT '排序号',
  `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态（0禁用,1可用）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='角色组表';

CREATE TABLE `tp_auth_group_access` (
  `uid` int unsigned NOT NULL COMMENT '用户ID',
  `group_id` int unsigned NOT NULL COMMENT '角色组ID',
  UNIQUE KEY `uid_group_id` (`uid`,`group_id`),
  KEY `uid` (`uid`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB COMMENT='用户角色组映射表';
 */

class Auth
{
    /**
     * @var object 对象实例
     */
    protected static $instance;
    /**
     * 当前请求实例.
     * @var Request
     */
    protected $request;
    protected $rules = [];
    // 默认配置
    protected $_config = [
        'auth_on'            => true,                // 认证开关
        'auth_type'          => 1,                   // 认证方式，1为实时认证；2为登录认证。
        'auth_group'         => 'auth_group',        // 用户组数据表名
        'auth_group_access'  => 'auth_group_access', // 用户-用户组关系表
        'auth_rule'          => 'auth_rule',         // 权限规则表
        'auth_user'          => 'administrator',     // 用户信息表
        'auth_user_id_field' => 'id',                // 用户表ID字段名
        'auth_root_user_id'  => 0,                   // 用户表根用户ID（超级用户）
    ];

    public function __construct()
    {
        if ($auth = Config::get('auth')) {
            $this->_config = array_merge($this->config, $auth);
        }
        // 初始化request
        $this->request = Request::instance();
    }

    /**
     * 初始化
     * @param  array $options 参数
     * @return Auth
     */
    public static function instance($options = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 检查权限
     * @param  string|array $name     需要验证的规则列表，支持逗号分隔的权限规则或索引数组
     * @param  integer      $uid      认证用户ID
     * @param  integer      $type     查询类型
     * @param  string       $mode     执行check的模式
     * @param  string       $relation 如果为 'or' 表示满足任一条规则即通过验证;如果为 'and' 则表示需满足所有规则才能通过验证
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @return boolean                通过验证返回true;失败返回false
     */
    public function check($name, $uid, $type = 1, $mode = 'url', $relation = 'or')
    {
        // 不需要验证时返回true
        if (!$this->_config['auth_on']) {
            return true;
        }

        // 根用户直接返回true
        if ($uid == $this->_config['auth_root_user_id']) {
            return true;
        }

        // 获取用户需要验证的所有有效规则列表
        $rulelist = $this->getRuleList($uid);

        // 支持泛类型，此时为所有权限
        if (in_array('*', $rulelist)) {
            return true;
        }

        if (is_string($name)) {
            $name = strtolower($name);
            if (strpos($name, ',') !== false) {
                $name = explode(',', $name);
            } else {
                $name = [$name];
            }
        }

        // 保存验证通过的规则名
        $list = [];
        if ($mode === 'url') {
            $REQUEST = unserialize(strtolower(serialize($this->request->param())));
        }

        foreach ($rulelist as $rule) {
            $query = preg_replace('/^.+\?/U', '', $rule);

            if ($mode === 'url' && $query != $rule) {
                // 解析规则中的param
                parse_str($query, $param);
                $intersect = array_intersect_assoc($REQUEST, $param);
                $rule = preg_replace('/\?.*$/U', '', $rule);

                // 如果节点相符且url参数满足
                if (in_array($rule, $name) && $intersect == $param) {
                    $list[] = $rule;
                }
            } else if (in_array($rule, $name)) {
                $list[] = $rule;
            }
        }

        if ($relation === 'or' && !empty($list)) {
            return true;
        }

        $diff = array_diff($name, $list);
        if ($relation === 'and' && empty($diff)) {
            return true;
        }

        return false;
    }

    /**
     * 根据用户ID获取用户组，返回值为数组
     * @param  integer $uid 用户ID
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @return array       用户所属用户组信息
     */
    public function getGroups($uid)
    {
        static $groups = [];

        if (isset($groups[$uid])) {
            return $groups[$uid];
        }

        $user_groups = Db::name($this->_config['auth_group_access'])->alias('acc')->join($this->_config['auth_group'] . ' gro', 'acc.group_id = gro.id', 'LEFT')->field('acc.uid, acc.group_id, gro.name, gro.rules')->where('acc.uid', $uid)->where('gro.status', 1)->select();
        $groups[$uid] = $user_groups ?: [];

        return $groups[$uid];
    }

    /**
     * 获取用户规则节点Ids
     * @param  integer $uid 用户ID
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @return array
     */
    public function getRuleIds($uid)
    {
        // 读取用户所属用户组
        $groups = $this->getGroups($uid);

        $ids = []; // 保存用户所属用户组设置的所有权限规则ID
        foreach ($groups as $g) {
            $ids = array_merge($ids, explode(',', trim($g['rules'], ',')));
        }
        $ids = array_unique($ids);

        return $ids;
    }

    /**
     * 获得权限规则列表
     * @param  integer        $uid  用户ID
     * @param  integer|string $type 查询类型
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @return array                权限规则列表
     */
    protected function getRulelist($uid, $type)
    {
        // 保存用户验证通过的权限列表
        static $_rulelist = [];
        $t = implode(',', (array) $type);

        if (isset($_rulelist[$uid . $t])) {
            return $_rulelist[$uid . $t];
        }

        // 可替换成Cache
        if ($this->_config['auth_type'] == 2 && Session::has('_rule_list_' . $uid . $t)) {
            return Session::get('_rule_list_' . $uid . $t);
        }

        // 获取用户规则节点Ids
        $ids = $this->getRuleIds($uid);
        if (empty($ids)) {
            $_rulelist[$uid . $t] = [];
            return [];
        }

        // 必要条件
        $map[] = ['status', '=', 1];

        // 筛选条件
        if (!in_array('*', $ids)) {
            $map[] = ['id', 'IN', $ids];
        }

        if (is_array($type)) {
            $map[] = ['type', 'IN', $type];
        } else {
            $map[] = ['type', '=', $type];
        }

        // 读取用户组所有权限规则
        $this->rules = Db::name($this->_config['auth_rule'])->where($map)->field('id, pid, name, title, condition')->select();

        // 循环规则，判断结果。
        $rulelist = [];

        if (in_array('*', $ids)) {
            $rulelist[] = "*";
        }

        foreach ($this->rules as $rule) {
            // 根据用户表的condition进行验证，超级管理员无需验证condition
            if (!empty($rule['condition']) && !in_array('*', $ids) && $uid != $this->_config['auth_root_user_id']) {
                $user = $this->getUserInfo($uid); // 获取用户信息,一维数组
                $command = preg_replace('/\{(\w*?)\}/', '$user[\'\\1\']', $rule['condition']);
                @(eval('$condition=(' . $command . ');'));

                if ($condition) {
                    $rulelist[] = strtolower($rule['name']);
                }
            } else {
                // 只要存在就记录
                $rulelist[] = strtolower($rule['name']);
            }
        }

        $_rulelist[$uid . $t] = $rulelist;

        // 如果是登录认证，则需要保存规则列表
        if ($this->_config['auth_type'] == 2) {
            Session::set('_rule_list_' . $uid . $t, $rulelist);
        }

        return array_unique($rulelist);
    }

    /**
     * 获得用户资料，根据自己的情况读取数据库
     * @param  integer $uid 用户ID
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @return array|null|\think\Model
     */
    protected function getUserInfo($uid)
    {
        static $userinfo = [];
        if (!isset($userinfo[$uid])) {
            $userinfo[$uid] = Db::name($this->_config['auth_user'])->where((string) $this->_config['auth_user_id_field'], $uid)->find();
        }

        return $userinfo[$uid];
    }
}
