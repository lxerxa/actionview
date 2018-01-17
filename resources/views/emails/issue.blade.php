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
    text-decoration:line-through
  }
  .cell-after {
    background:#DDFADE;
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
        <b>{{ $user['name'] }}</b> 
        @if ($event_key == 'create_issue') 创建了
        @elseif ($event_key == 'edit_issue' || $event_key == 'normal') 更新了
        @elseif ($event_key == 'del_issue') 删除了
        @elseif ($event_key == 'assign_issue') 分配了
        @elseif ($event_key == 'reset_issue') 重置了
        @elseif ($event_key == 'move_issue') 移动了
        @elseif ($event_key == 'start_progress_issue') 开始解决
        @elseif ($event_key == 'stop_progress_issue') 停止解决
        @elseif ($event_key == 'resolve_issue') 解决了
        @elseif ($event_key == 'close_issue') 关闭了
        @elseif ($event_key == 'reopen_issue') 重新打开
        @endif
        问题 <a href={{ $domain . '/project/' . $project['key'] . '/issue' . '?no=' . $issue['no'] }} target='_blank'>{{ $issue['title'] }}</a>
        @if ($event_key == 'create_issue')
        @elseif ($event_key == 'add_file') 上传了文档
        @elseif ($event_key == 'del_file') 删除了文档
        @elseif ($event_key == 'add_comments') 添加了备注
        @elseif ($event_key == 'edit_comments') 编辑了备注
        @elseif ($event_key == 'del_comments') 删除了备注
        @elseif ($event_key == 'add_worklog') 添加了工作日志
        @elseif ($event_key == 'edit_worklog') 编辑了工作日志
        @elseif ($event_key == 'del_worklog') 删除了工作日志
        @endif
        @if (isset($at) and $at)
          @了你
        @endif
      </td>
    </tr>
    <tr>
      <td style='padding: 0cm 15.0pt 0cm 15.0pt'>
        <table class='contents'>
          <tr>
            <td style='padding: 12.5pt 0cm 1.5pt 10pt;' colspan=2>
              <a href={{ $domain . '/project/' . $project['key'] }} target='_blank'>
                {{ $project['key'] }} - {{ $project['name'] }}
              </a>
              / 
              <a href={{ $domain . '/project/' . $project['key'] . '/issue' . '?no=' . $issue['no'] }} target='_blank'>
                {{ $project['key'] }} - {{ $issue['no'] }}
              </a>
            <td>
          </tr>
          <tr>
            <td style='padding: 2.5pt 0cm 12.5pt 10pt;' colspan=2>
              <a href={{ $domain . '/project/' . $project['key'] . '/issue' . '?no=' . $issue['no'] }} target='_blank'>
                <span style='font-size: 16px'>{{ $issue['title'] }}</span>
              </a>
            </td>
          </tr>
          @if ($event_key == 'create_issue')
            @foreach ($issue as $key => $field)
              @if ($key == 'no' or $key == 'title')
                @continue
              @endif
              <tr>
                <td class='cell-title' width='70pt'>
                  @if ($key == 'assignee') 经办人：
                  @elseif ($key == 'type') 类型：
                  @elseif ($key == 'priority') 优先级：
                  @elseif ($key == 'description') 描述：
                  @endif
                </td>
                <td class='cell'>
                {{ $field }}
                </td>
              </tr>
            @endforeach
          @elseif ($event_key == 'edit_issue' 
            or $event_key == 'assign_issue' 
            or $event_key == 'reset_issue' 
            or $event_key == 'move_issue' 
            or $event_key == 'start_progress_issue' 
            or $event_key == 'stop_progress_issue' 
            or $event_key == 'resolve_issue' 
            or $event_key == 'close_issue' 
            or $event_key == 'reopen_issue'
            or $event_key == 'normal')
            @foreach ($data as $item)
              <tr>
                <td class='cell-title' width='70pt'>
                  {{ $item['field'] }}：
                </td>
                <td class='cell'>
                  <span class='cell-before'>{{ $item['before_value'] }}</span>
                  <span class='cell-after'>{{ $item['after_value'] }}</span>
                </td>
              </tr>
            @endforeach
          @elseif ($event_key == 'add_file'
            or $event_key == 'del_file')
            <tr>
              <td class='cell-title' width='70pt'>
                文档:
              </td>
              <td class='cell'>
                <span style='text-decoration: {{ $event_key == "del_file" ? "line-through" : "none" }}'>{{ $data }}
              </td>
            </tr>
          @elseif ($event_key == 'add_comments'
            or $event_key == 'edit_comments'
            or $event_key == 'del_comments')
            <tr>
              <td class='cell-title' width='70pt'>
                备注:
              </td>
              <td class='cell'>
                {{ $data['contents'] }}
              </td>
            </tr>
          @elseif ($event_key == 'add_worklog' 
            or $event_key == 'edit_worklog' 
            or $event_key == 'del_worklog') 
            <tr>
              <td class='cell-title' width='70pt'>
                开始时间:
              </td>
              <td class='cell'>
                {{ $data['started_at'] }}
              </td>
            </tr>
            <tr>
              <td class='cell-title' width='70pt'>
                耗时:
              </td>
              <td class='cell'>
                {{ $data['spend'] }}
              </td>
            </tr>
            @if (isset($data['cut']) && $data['cut'])
              <tr>
                <td class='cell-title' width='70pt'>
                  剩余时间设置为:
                </td>
                <td class='cell'>
                  {{ $data['leave_estimate'] }}
                </td>
              </tr>
            @endif
            @if (isset($data['cut']) && $data['cut'])
              <tr>
                <td class='cell-title' width='70pt'>
                  剩余时间缩减:
                </td>
                <td class='cell'>
                  {{ $data['cut'] }}
                </td>
              </tr>
            @endif
            <tr>
              <td class='cell-title' width='70pt'>
                备注:
              </td>
              <td class='cell'>
                {{ $data['comments'] ?: '-' }}
              </td>
            </tr>
          @endif
          <tr>
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
