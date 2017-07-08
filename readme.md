# ActionView

提供了一个基于Web的、面向企业的、开源免费的问题需求跟踪工具，致力于做成开源版的Jira。

# Installation

系统要求：
> apache 2.4.7+  
> php 5.5.9+  
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

修改文件：  
> vendor/cartalyst/sentinel/src/Users/EloquentUser.php  
> use Illuminate\Database\Eloquent\Model; ==> use Jenssegers\Mongodb\Eloquent\Model;  

> vendor/cartalyst/sentinel/src/Activations/EloquentActivation.php   
> use Illuminate\Database\Eloquent\Model; ==> use Jenssegers\Mongodb\Eloquent\Model; 

> vendor/cartalyst/sentinel/src/Persistences/EloquentPersistence.php  
> use Illuminate\Database\Eloquent\Model; ==> use Jenssegers\Mongodb\Eloquent\Model;  

> vendor/cartalyst/sentinel/src/Activations/IlluminateActivationRepository.php  
> create函数(line 75)添加 $activation->completed = false;  

> config/database.php  
&#160; &#160; &#160; &#160;mongodb' => [  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'driver'   => 'mongodb',  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'host'     => env('DB_HOST', 'localhost'),  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'port'     => env('DB_PORT', 27017),  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'database' => env('DB_DATABASE', 'xxxx'),  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'username' => env('DB_USERNAME', 'xxxx'),  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'password' => env('DB_PASSWORD', 'xxxx'),  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'options' => array(  
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;'db' => 'jirax'   
&#160; &#160; &#160; &#160;&#160; &#160; &#160; &#160;)  
&#160; &#160; &#160; &#160;],  

执行db脚本：  
> aaaa  

## Feature

* 支持用户创建项目，项目不仅可引用全局配置方案，也可自定义本地方案。
* 各项目不仅可引用系统默认工作流，同时可自定义自己的工作流，工作流的每一步可进行精细控制，确保正确的人在正确的时间执行正确的操作。
* 简单易用的问题界面配置。
* 完备的权限控制模型。
* 强大的数据筛选功能，可定义自己的搜索器。
* 灵活可定制的消息通知方案。
* 支持用户在问题上添加工作日志。
* 支持用户针对问题发表评论。
* 简洁漂亮的UI，使用当前流行的前端框架ReactJS+Redux。
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

谢谢你能参与ActionView的开发当中。如果对系统有一些疑惑，或发现了一些bug，或建议增加新的feature，或对系统有一些改进时，欢迎在[issue board](https://github.com/lxerxa/actionview/issues)讨论。  

## License

ActionView 遵从许可证 [GNU General Public License version 3](http://www.gnu.org/licenses/gpl-3.0.html)

The ActionView is open-sourced software licensed under the [GNU General Public License version 3](http://www.gnu.org/licenses/gpl-3.0.html).
