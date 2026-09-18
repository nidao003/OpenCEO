@extends($layout)

@section('content')
<div class="maincontent">
    <div class="pageheader">
        <div class="pageicon"><span class="fa fa-building"></span></div>
        <div class="pagetitle">
            <h1>OpenCEO · CEO Desk</h1>
            <h5>Company Memory → Reports → Project Intelligence → Company State</h5>
        </div>
    </div>

    <div class="maincontentinner">
        <div class="row-fluid">
            <div class="span4">
                <div class="widgetbox">
                    <h4 class="widgettitle">公司基础信息</h4>
                    <div class="widgetcontent">
                        <form method="post" action="{{ BASE_URL }}/openceo/desk">
                            <input type="hidden" name="action" value="save_profile">
                            <label>公司名称</label>
                            <input class="span12" name="name" value="{{ $profile['name'] ?? '' }}">
                            <label>公司背景</label>
                            <textarea class="span12" rows="5" name="background">{{ $profile['background'] ?? '' }}</textarea>
                            <label>商业模式</label>
                            <textarea class="span12" rows="3" name="business_model">{{ $profile['business_model'] ?? '' }}</textarea>
                            <label>使命</label>
                            <input class="span12" name="mission" value="{{ $profile['mission'] ?? '' }}">
                            <label>愿景</label>
                            <input class="span12" name="vision" value="{{ $profile['vision'] ?? '' }}">
                            <label>当前战略重点（每行一项）</label>
                            <textarea class="span12" rows="4" name="strategic_focus">{{ implode("\n", $profile['strategic_focus'] ?? []) }}</textarea>
                            <button class="btn btn-primary" type="submit">保存公司记忆</button>
                        </form>
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">补充公司记忆</h4>
                    <div class="widgetcontent">
                        <form method="post" action="{{ BASE_URL }}/openceo/desk">
                            <input type="hidden" name="action" value="add_memory">
                            <input class="span12" name="title" placeholder="标题，例如：2026 战略方向">
                            <select name="memory_type" class="span12">
                                <option value="context">业务背景</option>
                                <option value="rule">经营规则</option>
                                <option value="terminology">公司术语</option>
                                <option value="strategy">战略</option>
                            </select>
                            <textarea class="span12" rows="5" name="content" placeholder="输入需要长期保留的公司背景、规则或知识"></textarea>
                            <button class="btn" type="submit">加入 Company Memory</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="span8">
                <div class="widgetbox">
                    <h4 class="widgettitle">公司状态</h4>
                    <div class="widgetcontent">
                        <div class="row-fluid">
                            <div class="span3"><strong>{{ count($companyState['projects'] ?? []) }}</strong><br>项目</div>
                            <div class="span3"><strong>{{ count($companyState['open_risks'] ?? []) }}</strong><br>开放风险</div>
                            <div class="span3"><strong>{{ count($companyState['open_actions'] ?? []) }}</strong><br>行动项</div>
                            <div class="span3"><strong>{{ $companyState['pending_project_candidates'] ?? 0 }}</strong><br>待确认项目</div>
                        </div>
                        <hr>
                        @foreach(($companyState['projects'] ?? []) as $item)
                            @php($p = $item['project'] ?? [])
                            @php($o = $item['ai_observation'] ?? [])
                            <div style="margin-bottom:12px;">
                                <strong>{{ $p['name'] ?? '' }}</strong>
                                <span class="label">{{ $o['health'] ?? 'unobserved' }}</span>
                                @if(isset($o['progress_estimate']) && $o['progress_estimate'] !== null)
                                    <span>AI观察进度 {{ $o['progress_estimate'] }}%</span>
                                @endif
                                @if(!empty($o['summary']))<div>{{ $o['summary'] }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">AI 发现的新项目 · 人工确认</h4>
                    <div class="widgetcontent">
                        @forelse($candidates as $candidate)
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="border-bottom:1px solid #eee;padding:10px 0;">
                                <input type="hidden" name="action" value="resolve_candidate">
                                <input type="hidden" name="candidate_id" value="{{ $candidate['id'] }}">
                                <strong>{{ $candidate['name'] }}</strong>
                                <span class="muted">confidence {{ $candidate['confidence'] }}</span>
                                <div style="margin-top:8px;">
                                    <select name="candidate_action">
                                        <option value="create">创建正式项目</option>
                                        <option value="link">关联已有项目</option>
                                        <option value="ignore">忽略</option>
                                    </select>
                                    <input name="project_name" value="{{ $candidate['name'] }}" placeholder="新项目名称">
                                    <select name="project_id">
                                        <option value="0">选择已有项目</option>
                                        @foreach($registry as $project)
                                            <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-small" type="submit">确认</button>
                                </div>
                            </form>
                        @empty
                            <p>当前没有待确认的新项目。</p>
                        @endforelse
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">企业微信人员待绑定</h4>
                    <div class="widgetcontent">
                        @forelse($pendingIdentities as $identity)
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="border-bottom:1px solid #eee;padding:10px 0;">
                                <input type="hidden" name="action" value="bind_identity">
                                <input type="hidden" name="identity_id" value="{{ $identity['id'] }}">
                                <strong>{{ $identity['display_name'] ?: $identity['external_user_id'] }}</strong>
                                <span class="muted">{{ $identity['external_user_id'] }}</span>
                                <select name="user_id">
                                    <option value="0">选择 Leantime 员工</option>
                                    @foreach($companyUsers as $user)
                                        <option value="{{ $user['id'] }}">{{ trim(($user['lastname'] ?? '').($user['firstname'] ?? '')) ?: $user['username'] }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-small" type="submit">确认绑定</button>
                            </form>
                        @empty
                            <p>当前没有待绑定的企业微信成员。</p>
                        @endforelse
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">管理输出</h4>
                    <div class="widgetcontent">
                        <form method="post" action="{{ BASE_URL }}/openceo/desk" style="margin-bottom:12px;">
                            <input type="hidden" name="action" value="generate_output">
                            <button class="btn btn-primary" name="output_type" value="weekly_summary" type="submit">生成本周总结</button>
                            <button class="btn" name="output_type" value="monthly_summary" type="submit">生成本月总结</button>
                            <button class="btn" name="output_type" value="meeting_agenda" type="submit">生成周会议程</button>
                            <button class="btn" name="output_type" value="ppt_outline" type="submit">生成 PPT 大纲</button>
                            <button class="btn" name="output_type" value="video_narration" type="submit">生成视频旁白</button>
                        </form>

                        <h5>周度总结</h5>
                        <pre style="white-space:pre-wrap;max-height:260px;overflow:auto;">{{ $weeklyOutput['content'] ?? '尚未生成' }}</pre>
                        <h5>月度总结 @if(!empty($monthlyOutput['status']))<span class="label">{{ $monthlyOutput['status'] }}</span>@endif</h5>
                        <pre style="white-space:pre-wrap;max-height:320px;overflow:auto;">{{ $monthlyOutput['content'] ?? '尚未生成' }}</pre>
                        @if(!empty($monthlyOutput['id']))
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="margin-top:10px;">
                                <input type="hidden" name="action" value="supplement_monthly">
                                <input type="hidden" name="output_id" value="{{ $monthlyOutput['id'] }}">
                                <textarea class="span12" rows="3" name="supplement" placeholder="人工补充：遗漏事项、最终结果、经营判断等"></textarea>
                                <button class="btn" type="submit">保存人工补充版本</button>
                            </form>
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="margin-top:6px;">
                                <input type="hidden" name="action" value="finalize_monthly">
                                <input type="hidden" name="output_id" value="{{ $monthlyOutput['id'] }}">
                                <button class="btn btn-success" type="submit">确认 Final 月度总结</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
