# ActionView

提供了一个后端基于laravel-framework、前端基于reactjs＋redux的问题需求跟踪工具，致力于做成开源版的Jira。前端代码库：[actionview-fe](https://github.com/lxerxa/actionview-fe)。  

# Demo

http://www.actionview.cn  

# Installation

系统要求：
> apache 2.4.7+  
> php 5.5.9+ (安装php-gd, 重新设置上传文件大小限制)  
> mongodb 2.4.9+  

全局安装composer：   
> curl -sS https://getcomposer.org/installer | php  
> mv composer.phar /usr/local/bin/composer

下载程序：
> git clone https://github.com/lxerxa/actionview.git actionview

安装依赖(安装过程若缺少某个系统组件可手动安装):
> cd actionview   
> composer install    
> chmod -R 777 storage    
> cd bootstrap   
> chmod -R 777 cache  
> cd ../  
> cp .env.example .env (修改数据库连接参数)  

修改文件：  
> vendor/cartalyst/sentinel/src/Users/EloquentUser.php  
> use Illuminate\Database\Eloquent\Model; ==> use Jenssegers\Mongodb\Eloquent\Model;  

> vendor/cartalyst/sentinel/src/Activations/EloquentActivation.php   
> use Illuminate\Database\Eloquent\Model; ==> use Jenssegers\Mongodb\Eloquent\Model; 

> vendor/cartalyst/sentinel/src/Persistences/EloquentPersistence.php  
> use Illuminate\Database\Eloquent\Model; ==> use Jenssegers\Mongodb\Eloquent\Model;  

> vendor/cartalyst/sentinel/src/Activations/IlluminateActivationRepository.php  
> create函数(line 75)添加 $activation->completed = false;  

> vendor/cartalyst/sentinel/src/Users/EloquentUser.php  
> $fillable 中添加 avatar 字段  

执行db脚本：  
> mongorestore -h 127.0.0.1 -u username -p secret -d dbname --drop ./dbdata  

## Feature

* 支持用户创建项目，项目不仅可引用全局配置方案，也可自定义本地方案。
* 各项目不仅可引用系统默认工作流，同时可自定义自己的工作流，工作流的每一步可进行精细控制，确保正确的人在正确的时间执行正确的操作。
* 支撑敏捷开发的看板视图。
* 简单易用的问题界面配置。
* 强大的数据筛选功能，可定义自己的过滤器。
* 完备的权限控制模型。
* 灵活可定制的消息通知方案。
* 不仅可查看某个问题的改动记录，还可浏览整个项目的活动日志。  
* 支持用户在问题上添加工作日志。
* 支持用户针对问题发表评论。
* 使用当前较流行的前后端技术框架，后端：php/laravel, 前端：ReactJS+Redux。
* 清晰的代码结构，方便进行二次开发。

## RoadMap

* 开发移动APP（React Native）。
* 开发敏捷看板（Agile Board）。
* 开发报告模块。
* 支持动态密码登录。
* 支持多语言。
* 增加wiki功能。


## Official Documentation

该系统工具的参考文档将发布于 [ActionView站点](http://actionview.cn/docs)。由于时间原因，暂未整理完成。

## Contributing

谢谢您能参与ActionView的开发当中。如果您对系统有什么疑惑，或发现了一些问题，或建议增加新的feature，或提出改进时，欢迎在[issue board](https://github.com/lxerxa/actionview/issues)中讨论，如果是前端相关的可以在[front-end issue board](https://github.com/lxerxa/actionview/issues)中讨论。如果发现有重大安全问题可发Email至：actionview@126.com。

## License

ActionView 遵从许可证 [ Apache License Version 2.0](https://www.apache.org/licenses/LICENSE-2.0)

The ActionView is open-sourced software licensed under the [ Apache License Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).
