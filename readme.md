# ActionView

![](https://img.shields.io/badge/language-php-orange.svg) ![](https://img.shields.io/badge/framework-laravel+reactjs-brightgreen.svg) ![](https://img.shields.io/badge/license-apache2.0-blue.svg)  

[English](https://github.com/lxerxa/actionview/blob/master/readme.md) | [中文](https://github.com/lxerxa/actionview/blob/master/readme-cn.md)

An issue tracking tool based on php laravel-framework in back-end and reactjs+redux in front-end, it's similar to Jira. You could find the front-end source code from [actionview-fe](https://github.com/lxerxa/actionview-fe).    

We are using Jira to do the task management and bug tracking, but found that the license fee is to too expensive, and Jira itself is to heavy, in the later phase of a project , maintain the global scheme is too hard, and the web user experience is not good as current popular SPA. That's why the idea of ActionView came up.  

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

# Installation

[Ubuntu Installation - Apache](https://github.com/lxerxa/actionview/wiki/Ubuntu-Installation(Apache))  
[Ubuntu Installation - Nginx](https://github.com/lxerxa/actionview/wiki/Ubuntu-Installation(Nginx))  
[CentOS Installation - Apache](https://github.com/lxerxa/actionview/wiki/CentOS-Installation(Apache))  
[CentOS Installation - Nginx](https://github.com/lxerxa/actionview/wiki/CentOS-Installation(Nginx))  
[Docker Installation](https://github.com/lxerxa/actionview/wiki/Docker-Installation)  

# Feature

* User created project supported, which could use either global configuration scheme, or local user defined scheme, realize the perfect combination of global configuration scheme and local configuration scheme.  
* Every project could use the default system workflow, and could define its own workflow, in which every step could be controlled accurately to make sure right people make right operation at right time.  
* Support Board view in agile development(Scrum and Kanban).  
* Support Gantt view.  
* Configure issue screen simply and easily.  
* Powerful issue filtering function, could define your own filter.    
* Complete access control model, support authorizing user group.    
* User defined message notification scheme.    
* Could check the activity history for a specified issue, and could view the activity log for the whole project.  
* User adding worklog to an issue supported.  
* User adding comments to an issue supported.  
* Team members may share and find the documents required for work.  
* Support wiki based on markdown.  
* Support statistical reports of various dimensions.  
* Synchronization and authentication based on LDAP users supported.  
* Integration with GitLab and GitHub through webhook.  
* Developed by using most popular framework both front-end and back-end side, back-end: php/laravel, front-end: ReactJS+Redux.  
* Installation by docker supported.  
* Clear code structure, easy for second development.  

# FAQ

[FAQ](https://github.com/lxerxa/actionview/wiki/FAQ)

# RoadMap

* Mobile App development  
* Code repository  
* Pipeline
* Support multi-language      

# Contributing

Thank you for considering contributing to the ActionView! If you have some doubts, find some issues, propose a new feature, or improvements of existing behavior, be willing to discuss in the [issue board](https://github.com/lxerxa/actionview/issues). The front-end issues may be discussed in the [front-end issue board](https://github.com/lxerxa/actionview/issues). If find some major security problems, please mail to: actionview@126.com.  


# License

The ActionView is open-sourced software licensed under the [ Apache License Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).    
