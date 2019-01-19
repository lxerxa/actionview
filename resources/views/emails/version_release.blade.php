<html>
<style type="text/css">
  body {
    font-family: Helvetica Neue,helvetica,lucida grande,lucida sans unicode,lucida,Hiragino Sans GB,Microsoft YaHei,WenQuanYi Micro Hei,sans-serif;
    font-size: 14px;
    line-height: 1.5;
    min-height: 100%;
    overflow: hidden;
  }
  a {
    color: #337ab7;
    text-decoration: none;
  }
  a:focus, a:hover {
    color: #23527c;
    text-decoration: underline;
  }
  .main {
    width:100%;
    background:whitesmoke;
    border-collapse:collapse;
    font-size:14px;
  }
  .title {
    padding: 7.5pt 15.0pt 7.5pt 15.0pt;
  }
  .contents {
    width:100%;
    background:white;
    border:1px solid #ccc;
    font-size:14px;
  }
  .cell-title {
    padding: 2.5pt 0cm 1.5pt 10pt;
    color:#707070;
    white-space: nowrap;
  }
  .cell {
    padding: 2.5pt 0cm 1.5pt 1.5pt;
  }
  .cell-before {
    background:#FFE7E7;
    padding:2px;
    display: inline-block;
    text-decoration:line-through
  }
  .cell-after {
    background:#DDFADE;
    display: inline-block;
    padding:2px;
  }
  .footer {
    padding: 7.5pt 15.0pt 10pt 15.0pt; 
    font-size: 12px;
  }
</style>
<body>
  <table class='main'>
    <tr>
      <td class='title'>
        <b>{{ $user['name'] }}</b> 发布了 
        <a href={{ $http_host . '/project/' . $project['key'] . '/issue?resolve_version=' . $resolve_version['id'] }} target='_blank'>
          版本-{{ $resolve_version['name'] }}
        </a>
      </td>
    </tr>
    <tr>
      <td style='padding: 0cm 15.0pt 0cm 15.0pt'>
        <table class='contents'>
          <tr>
            <td style='padding: 12.5pt 0cm 12.5pt 10pt;' colspan=2>
              <a href={{ $http_host . '/project/' . $project['key'] }} target='_blank'>
                {{ $project['key'] }} - {{ $project['name'] }}
              </a>
              /
              <a href={{ $http_host . '/project/' . $project['key'] . '/issue?resolve_version=' . $resolve_version['id'] }} target='_blank'>
                版本-{{ $resolve_version['name'] }}
              </a>
            <td>
          </tr>
          <tr colspan=2>
            <td class='cell-title'><b>发布解决问题列表：</b></td>
          </tr>
          @foreach ($released_issues as $key => $issue)
            <tr>
              <td class='cell-title' width='600pt'>
                <a href={{ $http_host . '/project/' . $project['key'] . '/issue?no=' . $issue['no'] }} target='_blank'>
                  {{ $project['key'] . '-' . $issue['no'] . ' ' . $issue['title'] }}
                </a>
              </td>
              <td class='cell'>
                {{ $issue['assignee']['name'] }}
              </td>
            </tr>
          @endforeach
          <tr colspan=2>
            <td class='cell-title'>共计问题： {{ count($released_issues) }} 个</td>
          </tr>
          <tr colspan=2>
            <td>&nbsp;</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td class='footer'>这条信息是由【ActionView】发送的。</td>
    </tr>
  </table>
</body>
</html>
