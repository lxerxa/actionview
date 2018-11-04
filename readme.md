# ActionView

![](https://img.shields.io/badge/language-php-orange.svg) ![](https://img.shields.io/badge/framework-laravel+reactjs-brightgreen.svg) ![](https://img.shields.io/badge/license-apache2.0-blue.svg)  

An issue tracking tool based on php laravel-framework in back-end and reactjs+redux in front-end, it's similar to Jira. You could find the front-end source code from [actionview-fe](https://github.com/lxerxa/actionview-fe).  
一个类Jira的问题需求跟踪工具，前端基于reactjs＋redux、后端基于php laravel-framework。前端代码库：[actionview-fe](https://github.com/lxerxa/actionview-fe)。  

We are using Jira to do the task management and bug tracking, but found that the license fee is to too expensive, and Jira itself is to heavy, in the later phase of a project , maintain the global scheme is too hard, and the web user experience is not good as current popular SPA. That's why the idea of ActionView came up.  
我们实际开发过程一直在用Jira进行任务管理和Bug跟踪，除了采购License价格不菲外，使用过程中觉得Jira还是有点重、全局方案配置到了后期越来越难维护、页面体验也不像现在流行的SPA那么好，所以有了做ActionView的想法。  

# Demo

http://www.actionview.cn  

![image](http://actionview.cn/summary.png)

![image](http://actionview.cn/issues-list.png)

![image](http://actionview.cn/workflow.png)

![image](http://actionview.cn/kanban-list.png)

![image](http://actionview.cn/kanban-drag.png)

![image](http://actionview.cn/kanban-backlog.png)

![image](http://actionview.cn/type.png)

# Installation

[Ubuntu Installation](https://github.com/lxerxa/actionview/wiki/Ubuntu-Installation)  
[CentOS Installation](https://github.com/lxerxa/actionview/wiki/CentOS-Installation)  
[Docker Installation](https://github.com/lxerxa/actionview/wiki/Docker-Installation)  

# Feature

* User created project supported, which could use either global configuration scheme, or local user defined scheme, realize the perfect combination of global configuration scheme and local configuration scheme.  
支持用户创建项目，项目不仅可引用全局配置方案，也可自定义本地方案，实现了全局配置方案和本地配置方案的完美结合。  
* Every project could use the default system workflow, and could define its own workflow, in which every step could be controlled accurately to make sure right people make right operation at right time.  
各项目不仅可引用系统默认工作流，同时可自定义自己的工作流，工作流的每一步可进行精细控制，确保正确的人在正确的时间执行正确的操作。  
* Support Board view in agile development(Scrum and Kanban).  
支持敏捷开发的看板视图(Kanban和Scrum)。  
* Configure issue screen simply and easily.  
简单易用的问题界面配置。  
* Powerful issue filtering function, could define your own filter.    
强大的问题筛选功能，可定义自己的过滤器。  
* Complete access control model, support authorizing user group.    
完备的权限控制模型，支持给用户组授权。  
* User defined message notification scheme.    
灵活可定制的消息通知方案。  
* Could check the activity history for a specified issue, and could view the activity log for the whole project.  
不仅可查看某个问题的改动记录，还可浏览整个项目的活动日志。  
* User adding worklog to an issue supported.  
支持用户在问题上添加工作日志。  
* User adding comments to an issue supported.  
支持用户针对问题发表评论。  
* Team members may share and find the documents required for work.  
团队成员可分享和查找工作所需的资料文档。  
* Support wiki based on markdown.  
支持基于markdown语法的wiki。  
* Synchronization and authentication based on LDAP users supported.  
支持基于LDAP用户的同步和认证。
* Integration with GitLab and GitHub through webhook.  
通过webhook集成GitLab和GitHub.  
* Developed by using most popular framework both front-end and back-end side, back-end: php/laravel, front-end: ReactJS+Redux.  
使用当前较流行的前后端技术框架，后端：php/laravel, 前端：ReactJS+Redux.
* Installation by docker supported.  
支持Docker安装。  
* Clear code structure, easy for second development.  
清晰的代码结构，方便进行二次开发。

# FAQ

[FAQ](https://github.com/lxerxa/actionview/wiki/FAQ)

# RoadMap

* Mobile App development (React Native)  
开发移动APP（React Native）。 
* BI module development  
开发BI模块。  
* Support multi-language  
支持多语言。  

# Official Documentation

Development document will be released on ActionView website, not ready yet due to tight time schedule  
该系统工具的参考文档将发布于 ActionView 站点。由于时间原因，暂未整理完成。  

# Contributing

Thank you for considering contributing to the ActionView! If you have some doubts, find some issues, propose a new feature, or improvements of existing behavior, be willing to discuss in the [issue board](https://github.com/lxerxa/actionview/issues). The front-end issues may be discussed in the [front-end issue board](https://github.com/lxerxa/actionview/issues). If find some major security problems, please mail to: actionview@126.com.  
谢谢您能参与ActionView的开发当中。如果您对系统有什么疑惑，或发现了一些问题，或建议增加新的feature，或提出改进时，欢迎在[issue board](https://github.com/lxerxa/actionview/issues)中讨论，如果是前端相关的可以在[front-end issue board](https://github.com/lxerxa/actionview/issues)中讨论。如果发现有重大安全问题可发Email至：actionview@126.com。  


# License

The ActionView is open-sourced software licensed under the [ Apache License Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).    
ActionView 遵从许可证 [ Apache License Version 2.0](https://www.apache.org/licenses/LICENSE-2.0)
