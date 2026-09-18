@extends($layout)

@section('content')
<div class="maincontent">
    <div class="pageheader">
        <div class="pageicon"><span class="fa fa-building"></span></div>
        <div class="pagetitle">
            <h1>{!! __('openceo.desk.title') !!}</h1>
            <h5>{!! __('openceo.desk.subtitle') !!}</h5>
        </div>
    </div>

    <div class="maincontentinner">
        <div class="row-fluid">
            <div class="span4">
                <div class="widgetbox">
                    <h4 class="widgettitle">{!! __('openceo.company_profile') !!}</h4>
                    <div class="widgetcontent">
                        <form method="post" action="{{ BASE_URL }}/openceo/desk">
                            <input type="hidden" name="action" value="save_profile">
                            <label>{!! __('openceo.company_name') !!}</label>
                            <input class="span12" name="name" value="{{ $profile['name'] ?? '' }}">
                            <label>{!! __('openceo.company_background') !!}</label>
                            <textarea class="span12" rows="5" name="background">{{ $profile['background'] ?? '' }}</textarea>
                            <label>{!! __('openceo.business_model') !!}</label>
                            <textarea class="span12" rows="3" name="business_model">{{ $profile['business_model'] ?? '' }}</textarea>
                            <label>{!! __('openceo.mission') !!}</label>
                            <input class="span12" name="mission" value="{{ $profile['mission'] ?? '' }}">
                            <label>{!! __('openceo.vision') !!}</label>
                            <input class="span12" name="vision" value="{{ $profile['vision'] ?? '' }}">
                            <label>{!! __('openceo.strategic_focus') !!}</label>
                            <textarea class="span12" rows="4" name="strategic_focus">{{ implode("\n", $profile['strategic_focus'] ?? []) }}</textarea>
                            <button class="btn btn-primary" type="submit">{!! __('openceo.save_company_memory') !!}</button>
                        </form>
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">{!! __('openceo.add_company_memory') !!}</h4>
                    <div class="widgetcontent">
                        <form method="post" action="{{ BASE_URL }}/openceo/desk">
                            <input type="hidden" name="action" value="add_memory">
                            <input class="span12" name="title" placeholder="{{ __('openceo.memory_title_placeholder') }}">
                            <select name="memory_type" class="span12">
                                <option value="context">{!! __('openceo.memory_type.context') !!}</option>
                                <option value="rule">{!! __('openceo.memory_type.rule') !!}</option>
                                <option value="terminology">{!! __('openceo.memory_type.terminology') !!}</option>
                                <option value="strategy">{!! __('openceo.memory_type.strategy') !!}</option>
                            </select>
                            <textarea class="span12" rows="5" name="content" placeholder="{{ __('openceo.memory_content_placeholder') }}"></textarea>
                            <button class="btn" type="submit">{!! __('openceo.add_to_company_memory') !!}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="span8">
                <div class="widgetbox">
                    <h4 class="widgettitle">{!! __('openceo.company_state') !!}</h4>
                    <div class="widgetcontent">
                        <div class="row-fluid">
                            <div class="span3"><strong>{{ count($companyState['projects'] ?? []) }}</strong><br>{!! __('openceo.projects') !!}</div>
                            <div class="span3"><strong>{{ count($companyState['open_risks'] ?? []) }}</strong><br>{!! __('openceo.open_risks') !!}</div>
                            <div class="span3"><strong>{{ count($companyState['open_actions'] ?? []) }}</strong><br>{!! __('openceo.actions') !!}</div>
                            <div class="span3"><strong>{{ $companyState['pending_project_candidates'] ?? 0 }}</strong><br>{!! __('openceo.pending_projects') !!}</div>
                        </div>
                        <hr>
                        @foreach(($companyState['projects'] ?? []) as $item)
                            @php($p = $item['project'] ?? [])
                            @php($o = $item['effective_observation'] ?? $item['ai_observation'] ?? [])
                            @php($overrides = $item['human_overrides'] ?? [])
                            @php($health = $o['health'] ?? 'unobserved')
                            <div style="margin-bottom:12px;">
                                <strong>{{ $p['name'] ?? '' }}</strong>
                                <span class="label">{!! __('openceo.health.'.$health) !!}</span>
                                @if(!empty($overrides))
                                    <span class="label label-info">{!! __('openceo.human_override_active') !!}</span>
                                @endif
                                @if(isset($o['progress_estimate']) && $o['progress_estimate'] !== null)
                                    <span>{!! __('openceo.ai_progress') !!} {{ $o['progress_estimate'] }}%</span>
                                @endif
                                @if(!empty($o['summary']))<div>{{ $o['summary'] }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">{!! __('openceo.new_projects_title') !!}</h4>
                    <div class="widgetcontent">
                        @forelse($candidates as $candidate)
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="border-bottom:1px solid #eee;padding:10px 0;">
                                <input type="hidden" name="action" value="resolve_candidate">
                                <input type="hidden" name="candidate_id" value="{{ $candidate['id'] }}">
                                <strong>{{ $candidate['name'] }}</strong>
                                <span class="muted">{!! __('openceo.confidence') !!} {{ $candidate['confidence'] }}</span>
                                <div style="margin-top:8px;">
                                    <select name="candidate_action">
                                        <option value="create">{!! __('openceo.candidate_action.create') !!}</option>
                                        <option value="link">{!! __('openceo.candidate_action.link') !!}</option>
                                        <option value="ignore">{!! __('openceo.candidate_action.ignore') !!}</option>
                                    </select>
                                    <input name="project_name" value="{{ $candidate['name'] }}" placeholder="{{ __('openceo.new_project_name') }}">
                                    <select name="project_id">
                                        <option value="0">{!! __('openceo.select_existing_project') !!}</option>
                                        @foreach($registry as $project)
                                            <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-small" type="submit">{!! __('openceo.confirm') !!}</button>
                                </div>
                            </form>
                        @empty
                            <p>{!! __('openceo.no_pending_candidates') !!}</p>
                        @endforelse
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">{!! __('openceo.wecom_pending') !!}</h4>
                    <div class="widgetcontent">
                        @forelse($pendingIdentities as $identity)
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="border-bottom:1px solid #eee;padding:10px 0;">
                                <input type="hidden" name="action" value="bind_identity">
                                <input type="hidden" name="identity_id" value="{{ $identity['id'] }}">
                                <strong>{{ $identity['display_name'] ?: $identity['external_user_id'] }}</strong>
                                <span class="muted">{{ $identity['external_user_id'] }}</span>
                                <select name="user_id">
                                    <option value="0">{!! __('openceo.select_leantime_employee') !!}</option>
                                    @foreach($companyUsers as $user)
                                        <option value="{{ $user['id'] }}">{{ trim(($user['lastname'] ?? '').($user['firstname'] ?? '')) ?: $user['username'] }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-small" type="submit">{!! __('openceo.confirm_binding') !!}</button>
                            </form>
                        @empty
                            <p>{!! __('openceo.no_pending_identities') !!}</p>
                        @endforelse
                    </div>
                </div>

                <div class="widgetbox">
                    <h4 class="widgettitle">{!! __('openceo.management_outputs') !!}</h4>
                    <div class="widgetcontent">
                        <form method="post" action="{{ BASE_URL }}/openceo/desk" style="margin-bottom:12px;">
                            <input type="hidden" name="action" value="generate_output">
                            <button class="btn btn-primary" name="output_type" value="weekly_summary" type="submit">{!! __('openceo.generate_weekly') !!}</button>
                            <button class="btn" name="output_type" value="monthly_summary" type="submit">{!! __('openceo.generate_monthly') !!}</button>
                            <button class="btn" name="output_type" value="meeting_agenda" type="submit">{!! __('openceo.generate_meeting') !!}</button>
                            <button class="btn" name="output_type" value="ppt_outline" type="submit">{!! __('openceo.generate_ppt') !!}</button>
                            <button class="btn" name="output_type" value="video_narration" type="submit">{!! __('openceo.generate_video') !!}</button>
                        </form>

                        <h5>{!! __('openceo.weekly_summary') !!}</h5>
                        <pre style="white-space:pre-wrap;max-height:260px;overflow:auto;">{{ $weeklyOutput['content'] ?? __('openceo.not_generated') }}</pre>
                        <h5>
                            {!! __('openceo.monthly_summary') !!}
                            @if(!empty($monthlyOutput['status']))
                                <span class="label">{!! __('openceo.output_status.'.$monthlyOutput['status']) !!}</span>
                            @endif
                        </h5>
                        <pre style="white-space:pre-wrap;max-height:320px;overflow:auto;">{{ $monthlyOutput['content'] ?? __('openceo.not_generated') }}</pre>

                        @if(!empty($monthlyOutput['id']))
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="margin-top:10px;">
                                <input type="hidden" name="action" value="supplement_monthly">
                                <input type="hidden" name="output_id" value="{{ $monthlyOutput['id'] }}">
                                <textarea class="span12" rows="3" name="supplement" placeholder="{{ __('openceo.human_supplement_placeholder') }}"></textarea>
                                <button class="btn" type="submit">{!! __('openceo.save_supplement') !!}</button>
                            </form>
                            <form method="post" action="{{ BASE_URL }}/openceo/desk" style="margin-top:6px;">
                                <input type="hidden" name="action" value="finalize_monthly">
                                <input type="hidden" name="output_id" value="{{ $monthlyOutput['id'] }}">
                                <button class="btn btn-success" type="submit">{!! __('openceo.confirm_final') !!}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
