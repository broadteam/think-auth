<?php
return [
    // 权限设置
    'auth_config' => [
        'auth_on'            => true,                // 认证开关
        'auth_type'          => 1,                   // 认证方式，1为实时认证；2为登录认证。
        'auth_group'         => 'auth_group',        // 用户组数据表名
        'auth_group_access'  => 'auth_group_access', // 用户-用户组关系表
        'auth_rule'          => 'auth_rule',         // 权限规则表
        'auth_user'          => 'administrator',     // 用户信息表
        'auth_user_id_field' => 'id',                // 用户表ID字段名
        'auth_root_user_id'  => 0                    // 用户表根用户ID（超级用户）
    ],
];
