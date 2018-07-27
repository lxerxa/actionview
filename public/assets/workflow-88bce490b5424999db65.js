webpackJsonp([22],{199:function(e,t,r){try{(function(){"use strict";function n(e){return e&&e.__esModule?e:{"default":e}}function o(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var i=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e},l=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),s=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},u=r(1),c=n(u),d=r(3),p=r(4),f=n(p),y=r(204).mermaidAPI,h=function(e){function t(e){o(this,t),s(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),y.initialize({startOnLoad:!1})}return a(t,e),l(t,[{key:"componentDidMount",value:function(){for(var e=this.props,t=e.collection,r=e.state,n=t.length,o="graph LR;S(( ))-->"+(t.length>0?t[0].name:"-")+";",a=function(e){return t[e].actions&&t[e].actions.length<=0?(o+=t[e].name+";","continue"):void f.default.map(t[e].actions,function(r){f.default.map(r.results,function(n){o+=t[e].name,o+="--"+r.name+"-->",o+=f.default.find(t,{id:n.step}).name+";"})})},i=0;i<n;i++){a(i)}if(r){var l=f.default.find(t,{state:r});l&&(o+=" style "+l.name+" fill:#ffffbd;")}y.render("div",o,null,document.getElementById("chart"))}},{key:"render",value:function(){var e=this.props,t=e.name,r=(e.collection,e.close);return c.default.createElement(d.Modal,i({},this.props,{onHide:r,backdrop:"static",bsSize:"large","aria-labelledby":"contained-modal-title-sm"}),c.default.createElement(d.Modal.Header,{closeButton:!0,style:{background:"#f0f0f0",height:"50px"}},c.default.createElement(d.Modal.Title,{id:"contained-modal-title-la"},"工作流预览",t?" - "+t:"")),c.default.createElement(d.Modal.Body,null,c.default.createElement("div",{className:"mermaid",id:"chart"})),c.default.createElement(d.Modal.Footer,null,c.default.createElement(d.Button,{onClick:r},"关闭")))}}],[{key:"propTypes",value:{name:u.PropTypes.string,state:u.PropTypes.string,collection:u.PropTypes.array.isRequired,close:u.PropTypes.func.isRequired},enumerable:!0}]),t}(u.Component);t.default=h,e.exports=t.default}).call(this)}finally{}},204:function(e,t){e.exports=window.mermaid},281:function(e,t,r){try{(function(){"use strict";function e(e){return(0,u.asyncFuncCreator)({constant:"WORKFLOW_INDEX",promise:function(t){return t.request({url:"/project/"+e+"/workflow"})}})}function n(e,t){return(0,u.asyncFuncCreator)({constant:"WORKFLOW_CREATE",promise:function(r){return r.request({url:"/project/"+e+"/workflow",method:"post",data:t})}})}function o(e,t){return(0,u.asyncFuncCreator)({constant:"WORKFLOW_UPDATE",promise:function(r){return r.request({url:"/project/"+e+"/workflow/"+t.id,method:"put",data:t})}})}function a(e){return{type:"WORKFLOW_SELECT",id:e}}function i(e){return{type:"WORKFLOW_DELETE_NOTIFY",id:e}}function l(e,t){return(0,u.asyncFuncCreator)({constant:"WORKFLOW_DELETE",id:t,promise:function(r){return r.request({url:"/project/"+e+"/workflow/"+t,method:"delete"})}})}function s(e,t){return(0,u.asyncFuncCreator)({constant:"WORKFLOW_PREVIEW",id:t,promise:function(r){return r.request({url:"/project/"+e+"/workflow/"+t+"/preview"})}})}Object.defineProperty(t,"__esModule",{value:!0}),t.index=e,t.create=n,t.update=o,t.select=a,t.delNotify=i,t.del=l,t.preview=s;var u=r(43)}).call(this)}finally{}},1887:function(e,t,r){try{(function(){"use strict";function n(e){if(e&&e.__esModule)return e;var t={};if(null!=e)for(var r in e)Object.prototype.hasOwnProperty.call(e,r)&&(t[r]=e[r]);return t.default=e,t}function o(e){return e&&e.__esModule?e:{"default":e}}function a(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function i(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}function l(e){return{actions:(0,y.bindActionCreators)(m,e)}}Object.defineProperty(t,"__esModule",{value:!0});var s=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e},u=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),c=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},d=r(1),p=o(d),f=r(40),y=r(41),h=r(281),m=n(h),v=r(1896),b=r(1897),w=function(e){function t(e){a(this,r),c(Object.getPrototypeOf(r.prototype),"constructor",this).call(this,e),this.pid=""}i(t,e),u(t,[{key:"goConfig",value:function(e){var t=this.props.location.pathname,r=void 0===t?"":t,n=r+"/"+e;this.context.router.push({pathname:n})}},{key:"index",value:function(){return regeneratorRuntime.async(function(e){for(;;)switch(e.prev=e.next){case 0:return e.next=2,regeneratorRuntime.awrap(this.props.actions.index(this.pid));case 2:return e.abrupt("return",this.props.workflow.ecode);case 3:case"end":return e.stop()}},null,this)}},{key:"create",value:function(e){return regeneratorRuntime.async(function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,regeneratorRuntime.awrap(this.props.actions.create(this.pid,e));case 2:return t.abrupt("return",this.props.workflow.ecode);case 3:case"end":return t.stop()}},null,this)}},{key:"update",value:function(e){return regeneratorRuntime.async(function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,regeneratorRuntime.awrap(this.props.actions.update(this.pid,e));case 2:return t.abrupt("return",this.props.workflow.ecode);case 3:case"end":return t.stop()}},null,this)}},{key:"del",value:function(e){var t;return regeneratorRuntime.async(function(r){for(;;)switch(r.prev=r.next){case 0:return t=this.props.actions,r.next=3,regeneratorRuntime.awrap(t.del(this.pid,e));case 3:return r.abrupt("return",this.props.workflow.ecode);case 4:case"end":return r.stop()}},null,this)}},{key:"preview",value:function(e){return regeneratorRuntime.async(function(t){for(;;)switch(t.prev=t.next){case 0:return t.next=2,regeneratorRuntime.awrap(this.props.actions.preview(this.pid,e));case 2:return t.abrupt("return",this.props.workflow.ecode);case 3:case"end":return t.stop()}},null,this)}},{key:"componentWillMount",value:function(){var e=this.props.location.pathname,t=void 0===e?"":e;if(/^\/admin\/scheme/.test(t))this.pid="$_sys_$";else{var r=this.props.params.key;this.pid=r}}},{key:"render",value:function(){var e=this.props.location.pathname,t=void 0===e?"":e;return p.default.createElement("div",null,p.default.createElement(v,s({isSysConfig:/^\/admin\/scheme/.test(t),create:this.create.bind(this),goConfig:this.goConfig.bind(this),i18n:this.props.i18n},this.props.workflow)),p.default.createElement(b,s({pkey:this.pid,index:this.index.bind(this),create:this.create.bind(this),select:this.props.actions.select,update:this.update.bind(this),del:this.del.bind(this),preview:this.preview.bind(this),goConfig:this.goConfig.bind(this),delNotify:this.props.actions.delNotify,i18n:this.props.i18n},this.props.workflow)))}}],[{key:"propTypes",value:{actions:d.PropTypes.object.isRequired,location:d.PropTypes.object.isRequired,params:d.PropTypes.object.isRequired,i18n:d.PropTypes.object.isRequired,workflow:d.PropTypes.object.isRequired},enumerable:!0},{key:"contextTypes",value:{router:d.PropTypes.object.isRequired},enumerable:!0}]);var r=t;return t=(0,f.connect)(function(e){var t=e.i18n,r=e.workflow;return{i18n:t,workflow:r}},l)(t)||t}(d.Component);t.default=w,e.exports=t.default}).call(this)}finally{}},1888:function(e,t,r){try{(function(){"use strict";function n(e){return e&&e.__esModule?e:{"default":e}}function o(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var i=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e},l=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),s=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},u=r(1),c=n(u),d=r(17),p=r(3),f=r(4),y=n(f),h=r(6),m=r(47),v=r(5),b=function(e){var t={};return e.name||(t.name="必填"),t},w=function(e){function t(e){o(this,r),s(Object.getPrototypeOf(r.prototype),"constructor",this).call(this,e),this.state={ecode:0},this.handleSubmit=this.handleSubmit.bind(this),this.handleCancel=this.handleCancel.bind(this)}a(t,e),l(t,[{key:"componentWillMount",value:function(){var e=this.props,t=e.initializeForm,r=e.data,n=y.default.clone(r);y.default.extend(n,{name:"复制 - "+r.name}),t(n)}},{key:"handleSubmit",value:function(){var e,t,r,n,o;return regeneratorRuntime.async(function(a){for(;;)switch(a.prev=a.next){case 0:return e=this.props,t=e.values,r=e.copy,n=e.close,a.next=6,regeneratorRuntime.awrap(r(y.default.mapKeys(t,function(e,t){return"id"==t?"source_id":t})));case 6:o=a.sent,0===o?(this.setState({ecode:0}),n(),h.notify.show("复制完成。","success",2e3)):this.setState({ecode:o});case 8:case"end":return a.stop()}},null,this)}},{key:"handleEntry",value:function(){m("input[name=name]").select()}},{key:"handleCancel",value:function(){var e=this.props,t=e.close,r=e.submitting;r||(this.setState({ecode:0}),t())}},{key:"render",value:function(){var e=this.props,t=e.i18n.errMsg,r=e.fields,n=r.id,o=r.name,a=r.description,l=e.handleSubmit,s=e.invalid,u=e.submitting,d=e.data;return c.default.createElement(p.Modal,i({},this.props,{onHide:this.handleCancel,onEntered:this.handleEntry,backdrop:"static","aria-labelledby":"contained-modal-title-sm"}),c.default.createElement(p.Modal.Header,{closeButton:!0,style:{background:"#f0f0f0",height:"50px"}},c.default.createElement(p.Modal.Title,{id:"contained-modal-title-la"},"复制工作流 - "+d.name)),c.default.createElement("form",{onSubmit:l(this.handleSubmit),onKeyDown:function(e){13==e.keyCode&&e.preventDefault()}},c.default.createElement(p.Modal.Body,null,c.default.createElement(p.FormControl,i({type:"hidden"},n)),c.default.createElement(p.FormGroup,{controlId:"formControlsText",validationState:o.touched&&o.error?"error":""},c.default.createElement(p.ControlLabel,null,c.default.createElement("span",{className:"txt-impt"},"*"),"新工作流名"),c.default.createElement(p.FormControl,i({disabled:u,type:"text"},o,{placeholder:"工作流名"})),o.touched&&o.error&&c.default.createElement(p.HelpBlock,{style:{"float":"right"}},o.error)),c.default.createElement(p.FormGroup,{controlId:"formControlsText"},c.default.createElement(p.ControlLabel,null,"描述"),c.default.createElement(p.FormControl,i({disabled:u,type:"text"},a,{placeholder:"描述内容"})))),c.default.createElement(p.Modal.Footer,null,c.default.createElement("span",{className:"ralign"},0!==this.state.ecode&&!u&&t[this.state.ecode]),c.default.createElement("img",{src:v,className:u?"loading":"hide"}),c.default.createElement(p.Button,{disabled:u||s,type:"submit"},"确定"),c.default.createElement(p.Button,{bsStyle:"link",disabled:u,onClick:this.handleCancel},"取消"))))}}],[{key:"propTypes",value:{i18n:u.PropTypes.object.isRequired,optionValues:u.PropTypes.array,submitting:u.PropTypes.bool,invalid:u.PropTypes.bool,values:u.PropTypes.object,fields:u.PropTypes.object,handleSubmit:u.PropTypes.func.isRequired,close:u.PropTypes.func.isRequired,data:u.PropTypes.object.isRequired,initializeForm:u.PropTypes.func.isRequired,copy:u.PropTypes.func.isRequired},enumerable:!0}]);var r=t;return t=(0,d.reduxForm)({form:"workflow",fields:["id","name","description"],validate:b})(t)||t}(u.Component);t.default=w,e.exports=t.default}).call(this)}finally{}},1889:function(e,t,r){try{(function(){"use strict";function n(e){return e&&e.__esModule?e:{"default":e}}function o(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var i=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e},l=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),s=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},u=r(1),c=n(u),d=r(17),p=r(3),f=r(4),y=n(f),h=r(6),m=r(5),v=function(e){var t={};return e.name||(t.name="必填"),t},b=function(e){function t(e){o(this,r),s(Object.getPrototypeOf(r.prototype),"constructor",this).call(this,e),this.state={ecode:0},this.handleSubmit=this.handleSubmit.bind(this),this.handleCancel=this.handleCancel.bind(this)}a(t,e),l(t,[{key:"handleSubmit",value:function(){var e,t,r,n,o,a,i,l,s;return regeneratorRuntime.async(function(u){for(;;)switch(u.prev=u.next){case 0:return e=this.props,t=e.values,r=e.create,n=e.close,o={id:0,name:"initial_action",results:[{step:1,status:"Underway"}]},u.next=7,regeneratorRuntime.awrap(r(y.default.assign(t,{contents:{initial_action:o,steps:[{id:1,name:"开始",state:"Open",actions:[]}]}})));case 7:a=u.sent,0===a?(this.setState({ecode:0}),n(),h.notify.show("新建完成，请配置流程。","success",2e3),i=this.props,l=i.data,s=i.goConfig,y.default.isEmpty(l)||s(l.id)):this.setState({ecode:a});case 9:case"end":return u.stop()}},null,this)}},{key:"handleCancel",value:function(){var e=this.props,t=e.close,r=e.submitting;r||(this.setState({ecode:0}),t())}},{key:"render",value:function(){var e=this.props,t=e.i18n.errMsg,r=e.fields,n=r.name,o=r.description,a=e.handleSubmit,l=e.invalid,s=e.submitting;return c.default.createElement(p.Modal,i({},this.props,{onHide:this.handleCancel,backdrop:"static","aria-labelledby":"contained-modal-title-sm"}),c.default.createElement(p.Modal.Header,{closeButton:!0,style:{background:"#f0f0f0",height:"50px"}},c.default.createElement(p.Modal.Title,{id:"contained-modal-title-la"},"新建工作流")),c.default.createElement("form",{onSubmit:a(this.handleSubmit),onKeyDown:function(e){13==e.keyCode&&e.preventDefault()}},c.default.createElement(p.Modal.Body,null,c.default.createElement(p.FormGroup,{controlId:"formControlsText",validationState:n.touched&&n.error?"error":""},c.default.createElement(p.ControlLabel,null,c.default.createElement("span",{className:"txt-impt"},"*"),"工作流名"),c.default.createElement(p.FormControl,i({disabled:s,type:"text"},n,{placeholder:"工作流名"})),n.touched&&n.error&&c.default.createElement(p.HelpBlock,{style:{"float":"right"}},n.error)),c.default.createElement(p.FormGroup,{controlId:"formControlsText"},c.default.createElement(p.ControlLabel,null,"描述"),c.default.createElement(p.FormControl,i({disabled:s,type:"text"},o,{placeholder:"描述内容"})))),c.default.createElement(p.Modal.Footer,null,c.default.createElement("span",{className:"ralign"},0!==this.state.ecode&&!s&&t[this.state.ecode]),c.default.createElement("img",{src:m,className:s?"loading":"hide"}),c.default.createElement(p.Button,{disabled:s||l,type:"submit"},"确定"),c.default.createElement(p.Button,{bsStyle:"link",disabled:s,onClick:this.handleCancel},"取消"))))}}],[{key:"propTypes",value:{i18n:u.PropTypes.object.isRequired,optionValues:u.PropTypes.array,submitting:u.PropTypes.bool,invalid:u.PropTypes.bool,values:u.PropTypes.object,fields:u.PropTypes.object,handleSubmit:u.PropTypes.func.isRequired,data:u.PropTypes.object,close:u.PropTypes.func.isRequired,create:u.PropTypes.func.isRequired,goConfig:u.PropTypes.func.isRequired},enumerable:!0}]);var r=t;return t=(0,d.reduxForm)({form:"workflow",fields:["name","description"],validate:v})(t)||t}(u.Component);t.default=b,e.exports=t.default}).call(this)}finally{}},1892:function(e,t,r){try{(function(){"use strict";function n(e){return e&&e.__esModule?e:{"default":e}}function o(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var i=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e},l=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),s=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},u=r(1),c=n(u),d=r(3),p=r(6),f=function(e){function t(e){o(this,t),s(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),this.confirm=this.confirm.bind(this),this.cancel=this.cancel.bind(this)}return a(t,e),l(t,[{key:"confirm",value:function(){var e,t,r,n,o;return regeneratorRuntime.async(function(a){for(;;)switch(a.prev=a.next){case 0:return e=this.props,t=e.close,r=e.del,n=e.data,t(),a.next=7,regeneratorRuntime.awrap(r(n.id));case 7:o=a.sent,0===o?p.notify.show("删除完成。","success",2e3):p.notify.show("删除失败。","error",2e3);case 9:case"end":return a.stop()}},null,this)}},{key:"cancel",value:function(){var e=this.props.close;e()}},{key:"render",value:function(){var e=this.props.data;return c.default.createElement(d.Modal,i({},this.props,{onHide:this.cancel,backdrop:"static","aria-labelledby":"contained-modal-title-sm"}),c.default.createElement(d.Modal.Header,{closeButton:!0,style:{background:"#f0f0f0",height:"50px"}},c.default.createElement(d.Modal.Title,{id:"contained-modal-title-la"},"删除工作流 - ",e.name)),c.default.createElement(d.Modal.Body,null,"确认要删除此工作流？"),c.default.createElement(d.Modal.Footer,null,c.default.createElement(d.Button,{onClick:this.confirm},"确定"),c.default.createElement(d.Button,{bsStyle:"link",onClick:this.cancel},"取消")))}}],[{key:"propTypes",value:{close:u.PropTypes.func.isRequired,del:u.PropTypes.func.isRequired,data:u.PropTypes.object.isRequired},enumerable:!0}]),t}(u.Component);t.default=f,e.exports=t.default}).call(this)}finally{}},1894:function(e,t,r){try{(function(){"use strict";function n(e){return e&&e.__esModule?e:{"default":e}}function o(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var i=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var r=arguments[t];for(var n in r)Object.prototype.hasOwnProperty.call(r,n)&&(e[n]=r[n])}return e},l=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),s=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},u=r(1),c=n(u),d=r(17),p=r(3),f=r(6),y=r(5),h=function(e){var t={};return e.name||(t.name="必填"),t},m=function(e){function t(e){o(this,r),s(Object.getPrototypeOf(r.prototype),"constructor",this).call(this,e),this.state={ecode:0},this.handleSubmit=this.handleSubmit.bind(this),this.handleCancel=this.handleCancel.bind(this)}a(t,e),l(t,[{key:"componentWillMount",value:function(){var e=this.props,t=e.initializeForm,r=e.data;t(r)}},{key:"handleSubmit",value:function(){var e,t,r,n,o;return regeneratorRuntime.async(function(a){for(;;)switch(a.prev=a.next){case 0:return e=this.props,t=e.values,r=e.update,n=e.close,a.next=6,regeneratorRuntime.awrap(r(t));case 6:o=a.sent,0===o?(this.setState({ecode:0}),n(),f.notify.show("更新完成。","success",2e3)):this.setState({ecode:o});case 8:case"end":return a.stop()}},null,this)}},{key:"handleCancel",value:function(){var e=this.props,t=e.close,r=e.submitting;r||(this.setState({ecode:0}),t())}},{key:"render",value:function(){var e=this.props,t=e.i18n.errMsg,r=e.fields,n=r.id,o=r.name,a=r.description,l=e.dirty,s=e.handleSubmit,u=e.invalid,d=e.submitting,f=e.data;return c.default.createElement(p.Modal,i({},this.props,{onHide:this.handleCancel,backdrop:"static","aria-labelledby":"contained-modal-title-sm"}),c.default.createElement(p.Modal.Header,{closeButton:!0,style:{background:"#f0f0f0",height:"50px"}},c.default.createElement(p.Modal.Title,{id:"contained-modal-title-la"},"编辑工作流 - "+f.name)),c.default.createElement("form",{onSubmit:s(this.handleSubmit),onKeyDown:function(e){13==e.keyCode&&e.preventDefault()}},c.default.createElement(p.Modal.Body,null,c.default.createElement(p.FormGroup,{controlId:"formControlsText",validationState:o.touched&&o.error?"error":""},c.default.createElement(p.FormControl,i({type:"hidden"},n)),c.default.createElement(p.ControlLabel,null,c.default.createElement("span",{className:"txt-impt"},"*"),"工作流名"),c.default.createElement(p.FormControl,i({disabled:d,type:"text"},o,{placeholder:"工作流名"})),o.touched&&o.error&&c.default.createElement(p.HelpBlock,{style:{"float":"right"}},o.error)),c.default.createElement(p.FormGroup,{controlId:"formControlsText"},c.default.createElement(p.ControlLabel,null,"描述"),c.default.createElement(p.FormControl,i({disabled:d,type:"text"},a,{placeholder:"描述内容"})))),c.default.createElement(p.Modal.Footer,null,c.default.createElement("span",{className:"ralign"},0!==this.state.ecode&&!d&&t[this.state.ecode]),c.default.createElement("img",{src:y,className:d?"loading":"hide"}),c.default.createElement(p.Button,{disabled:!l||d||u,type:"submit"},"确定"),c.default.createElement(p.Button,{bsStyle:"link",disabled:d,onClick:this.handleCancel},"取消"))))}}],[{key:"propTypes",value:{i18n:u.PropTypes.object.isRequired,optionValues:u.PropTypes.array,submitting:u.PropTypes.bool,invalid:u.PropTypes.bool,dirty:u.PropTypes.bool,values:u.PropTypes.object,fields:u.PropTypes.object,handleSubmit:u.PropTypes.func.isRequired,close:u.PropTypes.func.isRequired,data:u.PropTypes.object.isRequired,initializeForm:u.PropTypes.func.isRequired,update:u.PropTypes.func.isRequired},enumerable:!0}]);var r=t;return t=(0,d.reduxForm)({form:"workflow",fields:["id","name","description"],validate:h})(t)||t}(u.Component);t.default=m,e.exports=t.default}).call(this)}finally{}},1896:function(e,t,r){try{(function(){"use strict";function n(e){return e&&e.__esModule?e:{"default":e}}function o(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var i=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),l=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},s=r(1),u=n(s),c=r(3),d=r(4),p=n(d),f=r(1889),y=(r(5),function(e){function t(e){o(this,t),l(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),this.state={createModalShow:!1},this.createModalClose=this.createModalClose.bind(this)}return a(t,e),i(t,[{key:"createModalClose",value:function(){this.setState({createModalShow:!1})}},{key:"render",value:function(){var e=this,t=this.props,r=t.i18n,n=t.isSysConfig,o=t.create,a=t.goConfig,i=t.collection;return u.default.createElement("div",null,u.default.createElement("div",{style:{marginTop:"5px"}},u.default.createElement(c.Button,{className:"create-btn",onClick:function(){e.setState({createModalShow:!0})}},u.default.createElement("i",{className:"fa fa-plus"})," 新建工作流")),u.default.createElement("div",{className:"info-col"},u.default.createElement("div",{className:"info-icon"},u.default.createElement("i",{className:"fa fa-info-circle"})),u.default.createElement("div",{className:"info-content"},"只能删除没有关联到问题类型",n&&"（包括各项目自定义问题类型）","的工作流。")),this.state.createModalShow&&u.default.createElement(f,{show:!0,close:this.createModalClose,create:o,goConfig:a,data:i.length>0?p.default.last(i):{},i18n:r}))}}],[{key:"propTypes",value:{i18n:s.PropTypes.object.isRequired,collection:s.PropTypes.array.isRequired,isSysConfig:s.PropTypes.bool.isRequired,goConfig:s.PropTypes.func.isRequired,create:s.PropTypes.func.isRequired},enumerable:!0}]),t}(s.Component));t.default=y,e.exports=t.default}).call(this)}finally{}},1897:function(e,t,r){try{(function(){"use strict";function n(e){return e&&e.__esModule?e:{"default":e}}function o(e,t){if(!(e instanceof t))throw new TypeError("Cannot call a class as a function")}function a(e,t){if("function"!=typeof t&&null!==t)throw new TypeError("Super expression must either be null or a function, not "+typeof t);e.prototype=Object.create(t&&t.prototype,{constructor:{value:e,enumerable:!1,writable:!0,configurable:!0}}),t&&(Object.setPrototypeOf?Object.setPrototypeOf(e,t):e.__proto__=t)}Object.defineProperty(t,"__esModule",{value:!0});var i=function(){function e(e,t){for(var r=0;r<t.length;r++){var n=t[r];n.enumerable=n.enumerable||!1,n.configurable=!0,"value"in n&&(n.writable=!0),Object.defineProperty(e,n.key,n)}}return function(t,r,n){return r&&e(t.prototype,r),n&&e(t,n),t}}(),l=function(e,t,r){for(var n=!0;n;){var o=e,a=t,i=r;n=!1,null===o&&(o=Function.prototype);var l=Object.getOwnPropertyDescriptor(o,a);if(void 0!==l){if("value"in l)return l.value;var s=l.get;if(void 0===s)return;return s.call(i)}var u=Object.getPrototypeOf(o);if(null===u)return;e=u,t=a,r=i,n=!0,l=u=void 0}},s=r(1),u=n(s),c=(r(67),r(53)),d=r(3),p=r(4),f=(n(p),r(1894)),y=r(1888),h=r(1892),m=r(199),v=r(5),b=function(e){function t(e){o(this,t),l(Object.getPrototypeOf(t.prototype),"constructor",this).call(this,e),this.state={editModalShow:!1,copyModalShow:!1,delNotifyShow:!1,previewShow:!1,operateShow:!1,hoverRowId:""},this.editModalClose=this.editModalClose.bind(this),this.copyModalClose=this.copyModalClose.bind(this),this.delNotifyClose=this.delNotifyClose.bind(this),this.previewModalClose=this.previewModalClose.bind(this)}return a(t,e),i(t,[{key:"componentWillMount",value:function(){var e=this.props.index;e()}},{key:"editModalClose",value:function(){this.setState({editModalShow:!1})}},{key:"copyModalClose",value:function(){this.setState({copyModalShow:!1})}},{key:"delNotifyClose",value:function(){this.setState({delNotifyShow:!1})}},{key:"previewModalClose",value:function(){this.setState({previewModalShow:!1})}},{key:"operateSelect",value:function(e){var t,r,n,o,a,i;return regeneratorRuntime.async(function(l){for(;;)switch(l.prev=l.next){case 0:if(t=this.state.hoverRowId,r=this.props,n=r.select,o=r.preview,a=r.goConfig,i=0,"3"!==e){l.next=10;break}a(t),l.next=21;break;case 10:if("5"!==e){l.next=17;break}return l.next=13,regeneratorRuntime.awrap(o(t));case 13:i=l.sent,0===i&&this.setState({previewModalShow:!0}),l.next=21;break;case 17:n(t),"1"===e&&this.setState({editModalShow:!0}),"2"===e&&this.setState({delNotifyShow:!0}),"4"===e&&this.setState({copyModalShow:!0});case 21:case"end":return l.stop()}},null,this)}},{key:"onRowMouseOver",value:function(e){e.id!==this.state.hoverRowId&&this.setState({operateShow:!0,hoverRowId:e.id})}},{key:"onMouseLeave",value:function(){this.setState({operateShow:!1,hoverRowId:""})}},{key:"render",value:function(){for(var e=this.props,t=e.i18n,r=e.pkey,n=e.collection,o=e.selectedItem,a=(e.loading,e.indexLoading),i=e.itemLoading,l=e.itemSteps,s=e.del,p=e.update,b=e.create,w=this.state,g=w.operateShow,E=w.hoverRowId,O=u.default.createElement("span",null,u.default.createElement("i",{className:"fa fa-cog"})),P=[],T=n.length,k=0;k<T;k++){var C="$_sys_$"!==r&&"$_sys_$"===n[k].project_key;P.push({id:n[k].id,name:u.default.createElement("div",null,u.default.createElement("span",{className:"table-td-title"},n[k].name,C&&u.default.createElement("span",{style:{fontWeight:"normal"}}," (全局)")),n[k].description&&u.default.createElement("span",{className:"table-td-desc"},n[k].description)),latest_modify:u.default.createElement("span",null,n[k].latest_modified_time,u.default.createElement("br",null),n[k].latest_modifier&&n[k].latest_modifier.name),step:n[k].steps,operation:u.default.createElement("div",null,g&&E===n[k].id&&!i&&u.default.createElement(d.DropdownButton,{pullRight:!0,bsStyle:"link",style:{textDecoration:"blink",color:"#000"},title:O,key:k,id:"dropdown-basic-"+k,onSelect:this.operateSelect.bind(this)},u.default.createElement(d.MenuItem,{eventKey:"5"},"预览"),!C&&u.default.createElement(d.MenuItem,{
eventKey:"3"},"配置"),u.default.createElement(d.MenuItem,{eventKey:"4"},"复制"),!C&&u.default.createElement(d.MenuItem,{eventKey:"1"},"编辑"),!C&&!n[k].is_used&&u.default.createElement(d.MenuItem,{eventKey:"2"},"删除")),u.default.createElement("img",{src:v,className:i&&o.id===n[k].id?"loading":"hide"}))})}var j={};return a?j.noDataText=u.default.createElement("div",null,u.default.createElement("img",{src:v,className:"loading"})):j.noDataText="暂无数据显示。",j.onRowMouseOver=this.onRowMouseOver.bind(this),u.default.createElement("div",{style:{marginBottom:"30px"}},u.default.createElement(c.BootstrapTable,{pullRight:!0,data:P,bordered:!1,hover:!0,options:j,trClassName:"tr-top"},u.default.createElement(c.TableHeaderColumn,{dataField:"id",isKey:!0,hidden:!0},"ID"),u.default.createElement(c.TableHeaderColumn,{dataField:"name"},"名称"),u.default.createElement(c.TableHeaderColumn,{dataField:"latest_modify"},"最近配置修改"),u.default.createElement(c.TableHeaderColumn,{dataField:"step",width:"200"},"步骤"),u.default.createElement(c.TableHeaderColumn,{width:"60",dataField:"operation"})),this.state.editModalShow&&u.default.createElement(f,{show:!0,close:this.editModalClose,update:p,data:o,i18n:t}),this.state.copyModalShow&&u.default.createElement(y,{show:!0,close:this.copyModalClose,copy:b,data:o,i18n:t}),this.state.delNotifyShow&&u.default.createElement(h,{show:!0,close:this.delNotifyClose,data:o,del:s}),this.state.previewModalShow&&u.default.createElement(m,{show:!0,close:this.previewModalClose,collection:l,name:o.name}))}}],[{key:"propTypes",value:{i18n:s.PropTypes.object.isRequired,pkey:s.PropTypes.string.isRequired,collection:s.PropTypes.array.isRequired,itemSteps:s.PropTypes.array.isRequired,selectedItem:s.PropTypes.object.isRequired,loading:s.PropTypes.bool.isRequired,itemLoading:s.PropTypes.bool.isRequired,indexLoading:s.PropTypes.bool.isRequired,index:s.PropTypes.func.isRequired,select:s.PropTypes.func.isRequired,update:s.PropTypes.func.isRequired,create:s.PropTypes.func.isRequired,del:s.PropTypes.func.isRequired,preview:s.PropTypes.func.isRequired,goConfig:s.PropTypes.func.isRequired},enumerable:!0}]),t}(s.Component);t.default=b,e.exports=t.default}).call(this)}finally{}}});
//# sourceMappingURL=workflow-88bce490b5424999db65.js.map