# thinkphp-auth

适用于thinkphp权限扩展

## 安装

```php
composer require broadteam/think-auth
```

## 配置
```php
// 安装之后会在config目录里生成auth.php配置文件
return [
    // 认证开关
    'auth_on'            => true,
    // 认证方式，1为实时认证；2为登录认证
    'auth_type'          => 1,
    // 用户组数据表名
    'auth_group'         => 'auth_group',
    // 用户-用户组关系表
    'auth_group_access'  => 'auth_group_access',
    // 权限规则表
    'auth_rule'          => 'auth_rule',
    // 用户信息表
    'auth_user'          => 'administrator',
    // 用户表ID字段名
    'auth_user_id_field' => 'id',
    // 用户表根用户ID（超级用户）
    'auth_root_user_id'  => 0,
];
```

## 导入数据表

```sql
DROP TABLE IF EXISTS `tp_administrator`;
CREATE TABLE `tp_administrator` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(32) NOT NULL DEFAULT '' COMMENT '用户名',
  `fullname` varchar(32) NOT NULL DEFAULT '' COMMENT '昵称/全名',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `email_bind` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '邮箱已绑定',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `mobile_bind` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '手机已绑定',
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
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '-1软删除,0禁用,1可用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='管理员表';

DROP TABLE IF EXISTS `tp_auth_rule`;
CREATE TABLE `tp_auth_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '规则ID',
  `pid` int unsigned NOT NULL DEFAULT '0' COMMENT '节点父ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '节点标识',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '节点名称',
  `type` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '节点类型（0为权限节点，1为菜单）',
  `route` varchar(255) NOT NULL DEFAULT '' COMMENT '路由规则',
  `condition` varchar(255) NOT NULL DEFAULT '' COMMENT '规则条件（结合用户表其中的字段联合验证）',
  `icon` varchar(50) NOT NULL DEFAULT '' COMMENT '图标',
  `weight` int NOT NULL DEFAULT '0' COMMENT '权重',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`) USING BTREE,
  KEY `pid` (`pid`),
  KEY `weight` (`weight`)
) ENGINE=InnoDB COMMENT='角色规则节点表';

DROP TABLE IF EXISTS `tp_auth_group`;
CREATE TABLE `tp_auth_group` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '角色组ID',
  `pid` int unsigned NOT NULL DEFAULT '0' COMMENT '父组ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '组名',
  `rules` text NOT NULL COMMENT '规则IDS',
  `create_time` int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB COMMENT='角色组表';

DROP TABLE IF EXISTS `tp_auth_group_access`;
CREATE TABLE `tp_auth_group_access` (
  `uid` int unsigned NOT NULL COMMENT '用户ID',
  `group_id` int unsigned NOT NULL COMMENT '角色组ID',
  UNIQUE KEY `uid_group_id` (`uid`,`group_id`),
  KEY `uid` (`uid`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB COMMENT='用户角色组映射表';
```


## 用法示例

本类库的命名空间为： `namespace broadteam\think;`

或者使用 `$auth = new \broadteam\think\Auth();`

```
权限认证类
功能特性：
1、是对规则进行认证，不是对节点进行认证。用户可以把节点当作规则名称实现对节点进行认证。
  $auth = new \broadteam\think\Auth();  $auth->check('规则名称','用户id')
2、可以同时对多条规则进行认证，并设置多条规则的关系（or或者and）
  $auth = new \broadteam\think\Auth();  $auth->check('规则1,规则2','用户id','and')
  第三个参数为and时表示，用户需要同时具有规则1和规则2的权限。 当第三个参数为or时，表示用户值需要具备其中一个条件即可。默认为or
3、一个用户可以属于多个用户组(tp_auth_group_access表 定义了用户所属用户组)。我们需要设置每个用户组拥有哪些规则(tp_auth_group 定义了用户组权限)
4、支持规则表达式。
  在tp_auth_rule 表中定义一条规则时，如果type为1， condition字段就可以定义规则表达式。 如定义{score}>5  and {score}<100  表示用户的分数在5-100之间时这条规则才会通过。
```


```php
<?php
//高级实例  根据用户积分判断权限

//Auth类还可以按用户属性进行判断权限， 比如 按照用户积分进行判断，假设我们的用户表 (tp_admin) 有字段 score 记录了用户积分。我在规则表添加规则时，定义规则表的condition 字段，condition字段是规则条件，默认为空 表示没有附加条件，用户组中只有规则 就通过认证。如果定义了 condition字段，用户组中有规则不一定能通过认证，程序还会判断是否满足附加条件。 比如我们添加几条规则：

//name字段：grade1 condition字段：{score}<100
//name字段：grade2 condition字段：{score}>100 and {score}<200
//name字段：grade3 condition字段：{score}>200 and {score}<300

//这里 {score} 表示 think_members 表 中字段 score 的值。

//那么这时候

$auth = new \broadteam\think\Auth();
$auth->check('grade1', 1); //是判断用户积分是不是0-100
$auth->check('grade2', 1); //判断用户积分是不是在100-200
$auth->check('grade3', 1); //判断用户积分是不是在200-300
```
