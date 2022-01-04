webpackJsonp([27],{19:function(e,t,n){try{(function(){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var e=[{value:"Integer",label:"整数字段"},{value:"Number",label:"数值字段"},{value:"Text",label:"文本框单行"},{value:"TextArea",label:"文本框多行"},{value:"RichTextEditor",label:"富文本"},{value:"Select",label:"选择列表(单行)"},{value:"MultiSelect",label:"选择列表(多行)"},{value:"CheckboxGroup",label:"复选按钮"},{value:"RadioGroup",label:"单选按钮"},{value:"DatePicker",label:"日期选择控件"},{value:"DateTimePicker",label:"日期时间选择控件"},{value:"TimeTracking",label:"时间跟踪"},{value:"File",label:"文件"},{value:"SingleVersion",label:"单一版本选择"},{value:"MultiVersion",label:"多版本选择"},{value:"SingleUser",label:"单一用户选择"},{value:"MultiUser",label:"多用户选择"},{value:"Url",label:"URL"}];t.FieldTypes=e;var n=[{id:"new",name:"新建"},{id:"inprogress",name:"进行中"},{id:"completed",name:"完成"}];t.StateCategories=n;var r={project:[{id:"view_project",name:"查看项目"},{id:"manage_project",name:"管理项目"}],issue:[{id:"create_issue",name:"创建问题"},{id:"edit_issue",name:"编辑问题"},{id:"edit_self_issue",name:"编辑自己创建的问题"},{id:"delete_issue",name:"删除问题"},{id:"delete_self_issue",name:"删除自己创建的问题"},{id:"assign_issue",name:"分配问题"},{id:"assigned_issue",name:"被分配问题"},{id:"resolve_issue",name:"解决问题"},{id:"close_issue",name:"关闭问题"},{id:"reset_issue",name:"重置问题"},{id:"link_issue",name:"链接问题"},{id:"move_issue",name:"移动问题"},{id:"exec_workflow",name:"执行流程"}],comments:[{id:"add_comments",name:"添加评论"},{id:"edit_comments",name:"编辑评论"},{id:"edit_self_comments",name:"编辑自己的评论"},{id:"delete_comments",name:"删除评论"},{id:"delete_self_comments",name:"删除自己的评论"}],worklogs:[{id:"add_worklog",name:"添加工作日志"},{id:"edit_worklog",name:"编辑工作日志"},{id:"edit_self_worklog",name:"编辑自己的工作日志"},{id:"delete_worklog",name:"删除工作日志"},{id:"delete_self_worklog",name:"删除自己的工作日志"}],files:[{id:"upload_file",name:"上传附件"},{id:"download_file",name:"下载附件"},{id:"remove_file",name:"删除附件"},{id:"remove_self_file",name:"删除自己上传附件"}]};t.Permissions=r;var l=[{id:"create_issue",name:"创建问题"},{id:"edit_issue",name:"编辑问题"},{id:"del_issue",name:"删除问题"},{id:"resolve_issue",name:"解决问题"},{id:"close_issue",name:"关闭问题"},{id:"reopen_issue",name:"重新打开问题"},{id:"create_version",name:"创建版本"},{id:"edit_version",name:"编辑版本"},{id:"release_version",name:"发布版本"},{id:"merge_version",name:"合并版本"},{id:"del_version",name:"删除版本"},{id:"add_worklog",name:"添加工作日志"},{id:"edit_worklog",name:"编辑工作日志"}];t.webhookEvents=l;var a={CARD:"card",KANBAN_COLUMN:"kanban_column",KANBAN_FILTER:"kanban_filter"};t.CardTypes=a;var o=["#CCCCCC","#B3B3B3","#999999","#A4DD00","#68BC00","#006600","#73D8FF","#009CE0","#0062B1","#FCDC00","#FCC400","#FB9E00","#FE9200","#E27300","#C45100","#F44E3B","#D33115","#9F0500"];t.PriorityRGBs=o;var i=["#CCCCCC","#B3B3B3","#999999","#808080","#666666","#FDA1FF","#FA28FF","#AB149E","#AEA1FF","#7B64FF","#653294","#73D8FF","#009CE0","#0062B1","#68CCCA","#16A5A5","#0C797D","#A4DD00","#68BC00","#006600","#DBDF00","#B0BC00","#808900","#FCDC00","#FCC400","#FB9E00","#FE9200","#E27300","#C45100","#F44E3B","#D33115","#9F0500","#4D4D4D","#333333","#000000"];t.LabelRGBs=i;var u=600;t.DetailMinWidth=u;var s=1e3;t.DetailMaxWdith=s}).call(this)}finally{}},39:function(e,t,n){try{(function(){"use strict";function r(e){return e&&e.__esModule?e:{"default":e}}function l(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var o=function(){function e(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}return function(t,n,r){return n&&e(t.prototype,n),r&&e(t,r),t}}(),i=function(e,t,n){for(var r=!0;r;){var l=e,a=t,o=n;r=!1,null===l&&(l=Function.prototype);var i=Object.getOwnPropertyDescriptor(l,a);if(void 0!==i){if("value"in i)return i.value;var u=i.get;if(void 0===u)return;return u.call(o)}var s=Object.getPrototypeOf(l);if(null===s)return;e=s,t=a,n=o,r=!0,i=s=void 0}},u=n(1),s=r(u),c=n(15),d=function(e){function t(e){l(this,t),i(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),this.state={visible:!1},this.timer=null,this.scrollToTop=this.scrollToTop.bind(this)}return a(t,e),o(t,[{key:"componentDidMount",value:function(){var e=this,t=this.props.visibilityHeight,n=void 0===t?400:t,r=c(".doc-container");r.unbind("scroll").scroll(function(){var t=r.scrollTop();e.setState({visible:t>n})})}},{key:"componentWillUnmount",value:function(){c(".doc-container").unbind("scroll")}},{key:"scrollToTop",value:function(){var e=this,t=c(".doc-container"),n=50*(parseInt(t.scrollTop()/1e3)+1);cancelAnimationFrame(this.timer),this.timer=requestAnimationFrame(function r(){var l=t.scrollTop();l>0?(t.scrollTop(l-n>0?l-n:0),e.timer=requestAnimationFrame(r)):cancelAnimationFrame(e.timer)})}},{key:"render",value:function(){var e=this.state.visible,t=void 0!==e&&e;return s.default.createElement("div",{id:"backtop",className:"back-top",style:{visibility:t&&"visible"||"hidden"},onClick:this.scrollToTop},s.default.createElement("div",{className:"back-top-content"},s.default.createElement("div",{className:"back-top-icon"})))}}],[{key:"propTypes",value:{visibilityHeight:u.PropTypes.number},enumerable:!0}]),t}(u.Component);t.default=d,e.exports=t.default}).call(this)}finally{}},96:function(e,t,n){try{(function(){"use strict";function r(e){return e&&e.__esModule?e:{"default":e}}function l(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var o=function(){function e(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}return function(t,n,r){return n&&e(t.prototype,n),r&&e(t,r),t}}(),i=function(e,t,n){for(var r=!0;r;){var l=e,a=t,o=n;r=!1,null===l&&(l=Function.prototype);var i=Object.getOwnPropertyDescriptor(l,a);if(void 0!==i){if("value"in i)return i.value;var u=i.get;if(void 0===u)return;return u.call(o)}var s=Object.getPrototypeOf(l);if(null===s)return;e=s,t=a,n=o,r=!0,i=s=void 0}},u=n(1),s=r(u),c=n(2),d=n(3),f=r(d),p=n(101).mermaidAPI,m=function(e){function t(e){l(this,t),i(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),p.initialize({startOnLoad:!1})}return a(t,e),o(t,[{key:"componentDidMount",value:function(){for(var e=this.props,t=e.collection,n=e.state,r=t.length,l="graph LR;S(( ))-->"+(t.length>0?t[0].id+'["'+t[0].name+'"]':"-")+";",a=function(e){var n=f.default.escape(t[e].name);return t[e].actions&&t[e].actions.length<=0?(l+=t[e].id+'["'+n+'"];',"continue"):void f.default.map(t[e].actions,function(r){f.default.map(r.results,function(a){l+=t[e].id+'["'+n+'"]',l+='--"'+f.default.escape(r.name)+"("+r.id+')"-->';var o=f.default.find(t,{id:a.step});l+=o.id+'["'+f.default.escape(o.name)+'"];'})})},o=0;o<r;o++){a(o)}if(n){var i=f.default.find(t,{state:n});i&&(l+=" style "+i.id+" fill:#ffffbd;")}p.render("div",l,null,document.getElementById("chart"))}},{key:"render",value:function(){var e=this.props,t=e.name,n=(e.collection,e.close);return s.default.createElement(c.Modal,{show:!0,onHide:n,backdrop:"static",dialogClassName:"custom-modal-90","aria-labelledby":"contained-modal-title-sm"},s.default.createElement(c.Modal.Header,{closeButton:!0},s.default.createElement(c.Modal.Title,{id:"contained-modal-title-la"},"工作流预览",t?" - "+t:"")),s.default.createElement(c.Modal.Body,{style:{maxHeight:"580px",overflow:"auto"}},s.default.createElement("div",{className:"mermaid",id:"chart"})),s.default.createElement(c.Modal.Footer,null,s.default.createElement("span",{style:{"float":"left",marginTop:"7px"}},"提示：预览不支持IE"),s.default.createElement(c.Button,{onClick:n},"关闭")))}}],[{key:"propTypes",value:{name:u.PropTypes.string,state:u.PropTypes.string,collection:u.PropTypes.array.isRequired,close:u.PropTypes.func.isRequired},enumerable:!0}]),t}(u.Component);t.default=m,e.exports=t.default}).call(this)}finally{}},101:function(e,t){e.exports=window.mermaid},593:function(e,t,n){try{(function(){"use strict";function r(e){return e&&e.__esModule?e:{"default":e}}function l(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var o=function(){function e(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}return function(t,n,r){return n&&e(t.prototype,n),r&&e(t,r),t}}(),i=function(e,t,n){for(var r=!0;r;){var l=e,a=t,o=n;r=!1,null===l&&(l=Function.prototype);var i=Object.getOwnPropertyDescriptor(l,a);if(void 0!==i){if("value"in i)return i.value;var u=i.get;if(void 0===u)return;return u.call(o)}var s=Object.getPrototypeOf(l);if(null===s)return;e=s,t=a,n=o,r=!0,i=s=void 0}},u=n(1),s=r(u),c=n(2),d=n(3),f=r(d),p=n(19),m=function(e){function t(e){l(this,t),i(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),this.handleCancel=this.handleCancel.bind(this)}return a(t,e),o(t,[{key:"handleCancel",value:function(){var e=this.props.close;e()}},{key:"render",value:function(){var e=this.props,t=e.data,n=e.name;return s.default.createElement(c.Modal,{show:!0,onHide:this.handleCancel,backdrop:"static","aria-labelledby":"contained-modal-title-sm"},s.default.createElement(c.Modal.Header,{closeButton:!0},s.default.createElement(c.Modal.Title,{id:"contained-modal-title-la"},"预览界面",n?" - "+n:"")),s.default.createElement(c.Modal.Body,{style:{height:"420px",overflow:"auto"}},s.default.createElement(c.ListGroup,null,f.default.map(t,function(e,t){return s.default.createElement(c.ListGroupItem,{header:e.name||""},"键值:"+(e.key||"-")+" - 类型:"+(f.default.find(p.FieldTypes,{value:e.type})?f.default.find(p.FieldTypes,{value:e.type}).label:"")+(e.required?" - 必填":""))}))),s.default.createElement(c.Modal.Footer,null,s.default.createElement(c.Button,{onClick:this.handleCancel},"关闭")))}}],[{key:"propTypes",value:{close:u.PropTypes.func.isRequired,name:u.PropTypes.string,data:u.PropTypes.array.isRequired},enumerable:!0}]),t}(u.Component);t.default=m,e.exports=t.default}).call(this)}finally{}},1807:function(e,t,n){try{(function(){"use strict";function r(e){if(e&&e.__esModule)return e;var t={};if(null!=e)for(var n in e)Object.prototype.hasOwnProperty.call(e,n)&&(t[n]=e[n]);return t.default=e,t}function l(e){return e&&e.__esModule?e:{"default":e}}function a(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function o(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}function i(e){return{actions:(0,m.bindActionCreators)(y,e)}}Object.defineProperty(t,"__esModule",{value:!0});var u=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var r in n)Object.prototype.hasOwnProperty.call(n,r)&&(e[r]=n[r])}return e},s=function(){function e(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}return function(t,n,r){return n&&e(t.prototype,n),r&&e(t,r),t}}(),c=function(e,t,n){for(var r=!0;r;){var l=e,a=t,o=n;r=!1,null===l&&(l=Function.prototype);var i=Object.getOwnPropertyDescriptor(l,a);if(void 0!==i){if("value"in i)return i.value;var u=i.get;if(void 0===u)return;return u.call(o)}var s=Object.getPrototypeOf(l);if(null===s)return;e=s,t=a,n=o,r=!0,i=s=void 0}},d=n(1),f=l(d),p=n(21),m=n(22),v=n(2069),y=r(v),b=n(1808),h=function(e){function t(e){a(this,n),c(Object.getPrototypeOf(n.prototype),"constructor",this).call(this,e),this.pid=""}o(t,e),s(t,[{key:"index",value:function(){return regeneratorRuntime.async(function(e){for(;;)switch(e.prev=e.next){case 0:return e.next=2,regeneratorRuntime.awrap(this.props.actions.index(this.pid));case 2:return e.abrupt("return",this.props.config.ecode);case 3:case"end":return e.stop()}},null,this)}},{key:"componentWillMount",value:function(){var e=this.props,t=e.actions,n=e.params.key;t.index(n),this.pid=n}},{key:"render",value:function(){return f.default.createElement("div",null,f.default.createElement(b,u({index:this.index.bind(this),project:this.props.project.item},this.props.config)))}}],[{key:"propTypes",value:{actions:d.PropTypes.object.isRequired,location:d.PropTypes.object.isRequired,params:d.PropTypes.object.isRequired,project:d.PropTypes.object.isRequired,config:d.PropTypes.object.isRequired},enumerable:!0}]);var n=t;return t=(0,p.connect)(function(e){var t=e.project,n=e.config;return{project:t,config:n}},i)(t)||t}(d.Component);t.default=h,e.exports=t.default}).call(this)}finally{}},1808:function(e,t,n){try{(function(){"use strict";function r(e){return e&&e.__esModule?e:{"default":e}}function l(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var o=function(){function e(e,t){for(var n=0;n<t.length;n++){var r=t[n];r.enumerable=r.enumerable||!1,r.configurable=!0,"value"in r&&(r.writable=!0),Object.defineProperty(e,r.key,r)}}return function(t,n,r){return n&&e(t.prototype,n),r&&e(t,r),t}}(),i=function(e,t,n){for(var r=!0;r;){var l=e,a=t,o=n;r=!1,null===l&&(l=Function.prototype);var i=Object.getOwnPropertyDescriptor(l,a);if(void 0!==i){if("value"in i)return i.value;var u=i.get;if(void 0===u)return;return u.call(o)}var s=Object.getPrototypeOf(l);if(null===s)return;e=s,t=a,n=o,r=!0,i=s=void 0}},u=n(1),s=r(u),c=(n(20),n(2)),d=(n(5),n(3)),f=r(d),p=n(19),m=n(4),v=n(39),y=n(96),b=n(593),h=function(e){function t(e){var n=this;l(this,t),i(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),this.state={screenPreviewModalShow:!1,screenSchema:[],screenName:"",wfPreviewModalShow:!1,wfSteps:[],wfName:""},this.allPermissions=[],f.default.forEach(p.Permissions,function(e){n.allPermissions=n.allPermissions.concat(e)})}return a(t,e),o(t,[{key:"classifyPermissions",value:function(e){var t=[],n=[{key:"project",name:"项目"},{key:"issue",name:"问题"},{key:"files",name:"附件"},{key:"comments",name:"评论"},{key:"worklogs",name:"工作日志"}];return f.default.forEach(n,function(n){var r=f.default.filter(p.Permissions[n.key],function(t){return e.indexOf(t.id)!==-1});if(!(r.length<=0)){var l=s.default.createElement("li",{style:{display:"table",marginBottom:"5px"}},s.default.createElement("div",{style:{marginLeft:"5px"}},n.name),f.default.map(r,function(e){return s.default.createElement("div",{style:{"float":"left",margin:"0px 3px 6px 3px"}},s.default.createElement(c.Label,{style:{color:"#007eff",border:"1px solid #c2e0ff",backgroundColor:"#ebf5ff",fontWeight:"normal"},key:e.id},e.name))}));t.push(l)}}),s.default.createElement("ul",{style:{marginBottom:"0px",paddingLeft:"0px",listStyle:"none"}},t.length<=0?"-":t)}},{key:"render",value:function(){var e=this,t=this.props,n=(t.project,t.data),r=(t.options,t.loading);return r?s.default.createElement("div",{style:{marginTop:"50px",textAlign:"center"}},s.default.createElement("img",{src:m,className:"loading"})):s.default.createElement("div",{style:{marginTop:"15px",marginBottom:"30px"}},s.default.createElement(v,null),s.default.createElement(c.Panel,{header:"问题类型"},n.types&&n.types.length>0?s.default.createElement(c.Table,{responsive:!0,hover:!0},s.default.createElement("thead",null,s.default.createElement("tr",null,s.default.createElement("th",null,"名称"),s.default.createElement("th",null,"类型"),s.default.createElement("th",null,"界面"),s.default.createElement("th",null,"工作流"))),s.default.createElement("tbody",null,f.default.map(n.types,function(t){return s.default.createElement("tr",null,s.default.createElement("td",null,s.default.createElement("span",{className:"table-td-title-nobold"},t.name||"","(",t.abb||"",")",t.default&&s.default.createElement("span",{style:{fontWeight:"normal"}}," (默认)"),"subtask"==t.type&&s.default.createElement("span",{style:{fontWeight:"normal"}}," (子任务)")),s.default.createElement("span",{className:"table-td-desc"},t.description||"")),s.default.createElement("td",null,"subtask"===t.type?"子任务":"标准"),s.default.createElement("td",null,s.default.createElement("a",{href:"#",onClick:function(n){n.preventDefault(),e.setState({screenPreviewModalShow:!0,screenSchema:t.screen&&t.screen.schema||[],screenName:t.screen&&t.screen.name||""})}},t.screen&&t.screen.name||"")),s.default.createElement("td",null,s.default.createElement("a",{href:"#",onClick:function(n){n.preventDefault(),e.setState({wfPreviewModalShow:!0,wfSteps:t.workflow&&t.workflow.contents&&t.workflow.contents.steps||[],wfName:t.workflow&&t.workflow.name||""})}},t.workflow&&t.workflow.name||"")))}))):s.default.createElement("div",null,"暂无信息")),s.default.createElement(c.Panel,{header:"问题优先级"},n.priorities&&n.priorities.length>0?s.default.createElement(c.Table,{responsive:!0,hover:!0},s.default.createElement("thead",null,s.default.createElement("tr",null,s.default.createElement("th",null,"名称"),s.default.createElement("th",null,"图案"),s.default.createElement("th",null,"描述"))),s.default.createElement("tbody",null,f.default.map(n.priorities||[],function(e){return s.default.createElement("tr",null,s.default.createElement("td",null,s.default.createElement("span",{className:"table-td-title-nobold"},e.name||"",e.default&&s.default.createElement("span",{style:{fontWeight:"normal"}}," (默认)"))),s.default.createElement("td",null,s.default.createElement("div",{className:"circle",style:{backgroundColor:e.color||"#ccc"}})),s.default.createElement("td",null,e.description||"-"))}))):s.default.createElement("div",null,"暂无信息")),s.default.createElement(c.Panel,{header:"项目角色"},n.roles&&n.roles.length>0?s.default.createElement(c.Table,{responsive:!0,hover:!0},s.default.createElement("thead",null,s.default.createElement("tr",null,s.default.createElement("th",{style:{width:"300px"}},"名称"),s.default.createElement("th",null,"权限"))),s.default.createElement("tbody",null,f.default.map(n.roles,function(t){return s.default.createElement("tr",null,s.default.createElement("td",null,s.default.createElement("span",{className:"table-td-title-nobold"},t.name||""),s.default.createElement("span",{className:"table-td-desc"},t.description||"")),s.default.createElement("td",null,s.default.createElement("div",{style:{display:"table",width:"100%"}},t.permissions&&t.permissions.length>0?e.classifyPermissions(t.permissions):s.default.createElement("span",null,s.default.createElement("div",{style:{display:"inline-block",margin:"3px 3px 6px 3px"}},"-")))))}))):s.default.createElement("div",null,"暂无信息")),this.state.wfPreviewModalShow&&s.default.createElement(y,{show:!0,close:function(){e.setState({wfPreviewModalShow:!1})},name:this.state.wfName,collection:this.state.wfSteps}),this.state.screenPreviewModalShow&&s.default.createElement(b,{show:!0,close:function(){e.setState({screenPreviewModalShow:!1})},name:this.state.screenName,data:this.state.screenSchema}))}}],[{key:"propTypes",value:{project:u.PropTypes.object.isRequired,data:u.PropTypes.object.isRequired,options:u.PropTypes.object.isRequired,loading:u.PropTypes.bool.isRequired,index:u.PropTypes.func.isRequired},enumerable:!0}]),t}(u.Component);t.default=h,e.exports=t.default}).call(this)}finally{}},2069:function(e,t,n){try{(function(){"use strict";function e(e){return(0,r.asyncFuncCreator)({constant:"PROJECT_CONFIG",promise:function(t){return t.request({url:"/project/"+e+"/config"})}})}Object.defineProperty(t,"__esModule",{value:!0}),t.index=e;var r=n(26)}).call(this)}finally{}}});
//# sourceMappingURL=config-24b5328a3b9319e4faa8.js.map