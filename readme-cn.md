# ActionView

![](https://img.shields.io/badge/language-php-orange.svg) ![](https://img.shields.io/badge/framework-laravel+reactjs-brightgreen.svg) ![](https://img.shields.io/badge/license-apache2.0-blue.svg)  

[English](https://github.com/lxerxa/actionview/blob/master/readme.md) | [中文](https://github.com/lxerxa/actionview/blob/master/readme-cn.md)

一个类Jira的问题需求跟踪工具，前端基于reactjs＋redux、后端基于php laravel-framework。前端代码库：[actionview-fe](https://github.com/lxerxa/actionview-fe)。  

我们实际开发过程一直在用Jira进行任务管理和Bug跟踪，除了采购License价格不菲外，使用过程中觉得Jira还是有点重、全局方案配置到了后期越来越难维护、页面体验也不像现在流行的SPA那么好，所以有了做ActionView的想法。  

# Demo

http://www.actionview.cn  

![image](http://actionview.cn/images/summary.png)

![image](http://actionview.cn/images/issues.png)

![image](http://actionview.cn/images/workflow.png)

![image](http://actionview.cn/images/kanban.png)

![image](http://actionview.cn/images/kanban-drag.png)

![image](http://actionview.cn/images/kanban-backlog.png)

![image](http://actionview.cn/images/report.png)

![image](http://actionview.cn/images/gantt.png)

# 安装手册

[Ubuntu Installation - Apache](https://github.com/lxerxa/actionview/wiki/Ubuntu-Installation(Apache))  
[Ubuntu Installation - Nginx](https://github.com/lxerxa/actionview/wiki/Ubuntu-Installation(Nginx))  
[CentOS Installation - Apache](https://github.com/lxerxa/actionview/wiki/CentOS-Installation(Apache))  
[CentOS Installation - Nginx](https://github.com/lxerxa/actionview/wiki/CentOS-Installation(Nginx))  
[Docker Installation](https://github.com/lxerxa/actionview/wiki/Docker-Installation)  

# Feature

* 支持用户创建项目，项目不仅可引用全局配置方案，也可自定义本地方案，实现了全局配置方案和本地配置方案的完美结合。  
* 各项目不仅可引用系统默认工作流，同时可自定义自己的工作流，工作流的每一步可进行精细控制，确保正确的人在正确的时间执行正确的操作。  
* 支持敏捷开发的看板视图(Kanban和Scrum)。  
* 支持甘特图视图。  
* 简单易用的问题界面配置。  
* 强大的问题筛选功能，可定义自己的过滤器。  
* 完备的权限控制模型，支持给用户组授权。  
* 灵活可定制的消息通知方案。  
* 不仅可查看某个问题的改动记录，还可浏览整个项目的活动日志。  
* 支持用户在问题上添加工作日志。  
* 支持用户针对问题发表评论。  
* 团队成员可分享和查找工作所需的资料文档。  
* 支持基于markdown语法的wiki。 
* 支持各种维度的统计报表。  
* 支持基于LDAP用户的同步和认证。  
* 通过webhook集成GitLab和GitHub.  
* 使用当前较流行的前后端技术框架，后端：php/laravel, 前端：ReactJS+Redux.
* 支持Docker安装。  
* 清晰的代码结构，方便进行二次开发。

# 常见问题

[FAQ](https://github.com/lxerxa/actionview/wiki/FAQ)

# RoadMap

* 开发移动APP  
* 代码托管仓库  
* 流水线  
* 支持多语言    

# Contributing

谢谢您能参与ActionView的开发当中。如果您对系统有什么疑惑，或发现了一些问题，或建议增加新的feature，或提出改进时，欢迎在[issue board](https://github.com/lxerxa/actionview/issues)中讨论，如果是前端相关的可以在[front-end issue board](https://github.com/lxerxa/actionview/issues)中讨论。如果发现有重大安全问题可发Email至：actionview@126.com。  


# License

ActionView 遵从许可证 [ Apache License Version 2.0](https://www.apache.org/licenses/LICENSE-2.0)
