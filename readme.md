# ActionView

提供了一个基于Web的、开源的、免费的问题需求跟踪工具，致力于做成开源版的Jira。

# Installation

系统要求：  
> php 5.5.9+  

全局安装composer：   
> curl -sS https://getcomposer.org/installer | php  
> mv composer.phar /usr/local/bin/composer

下载程序：
> git clone https://github.com/lxerxa/actionview.git actionview

安装ActionView:  
> cd actionview  
> composer install  
> chmod -R 777 storage  
> cd bootstrap   
> chmod -R 777 cache  

## Feature

* 支持用户创建项目，项目不仅可引用全局配置方案，也可自定义本地方案。
* 各项目不仅可引用系统默认工作流，同时可自定义自己的工作流，工作流的每一步可进行精细控制，确保正确的人在正确的时间执行正确的操作。
* 简单易用的问题界面配置。
* 完备的权限控制模型。
* 强大的数据筛选功能，可定义自己的搜索器。
* 灵活可定制的消息通知方案。
* 支持用户在问题上添加工作日志。
* 简洁漂亮的UI，使用当前流行的前端框架ReactJS+Redux。

## RoadMap

* 开发移动APP（React Native）。
* 开发敏捷看板（Agile Board）。
* 开发报告模块。
* 支持动态密码登录。
* 支持多语言。


## Official Documentation

该系统工具的参考文档将发布于 [ActionView站点](http://actionview.cn/docs)。由于时间原因，暂未整理完成。

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](http://laravel.com/docs/contributions).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell at taylor@laravel.com. All security vulnerabilities will be promptly addressed.

## License

ActionView 遵从许可证 [GNU General Public License version 3](http://www.gnu.org/licenses/gpl-3.0.html)

The ActionView is open-sourced software licensed under the [GNU General Public License version 3](http://www.gnu.org/licenses/gpl-3.0.html).
