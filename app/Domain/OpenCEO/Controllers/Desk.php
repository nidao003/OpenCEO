<?php

namespace Leantime\Domain\OpenCEO\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\OpenCEO\Services\AgentGateway;
use Leantime\Domain\OpenCEO\Services\Company;
use Leantime\Domain\OpenCEO\Services\Outputs;
use Leantime\Domain\OpenCEO\Services\People;
use Leantime\Domain\OpenCEO\Services\Projects;
use Leantime\Domain\OpenCEO\Services\State;
use Symfony\Component\HttpFoundation\Response;

class Desk extends Controller
{
    private Company $company;
    private Projects $projects;
    private State $state;
    private Outputs $outputs;
    private People $people;
    private AgentGateway $agentGateway;

    public function init(
        Company $company,
        Projects $projects,
        State $state,
        Outputs $outputs,
        People $people,
        AgentGateway $agentGateway,
    ): void {
        $this->company = $company;
        $this->projects = $projects;
        $this->state = $state;
        $this->outputs = $outputs;
        $this->people = $people;
        $this->agentGateway = $agentGateway;
    }

    public function show(array $params): Response
    {
        Auth::authOrRedirect([Roles::$owner, Roles::$admin, Roles::$manager], true);
        $this->tpl->assign('profile', $this->company->getProfile());
        $this->tpl->assign('companyState', $this->state->current());
        $this->tpl->assign('candidates', $this->projects->listCandidates('pending', 100));
        $this->tpl->assign('registry', $this->projects->registry());
        $this->tpl->assign('weeklyOutput', $this->outputs->latest('weekly_summary'));
        $this->tpl->assign('monthlyOutput', $this->outputs->latest('monthly_summary'));
        $this->tpl->assign('pendingIdentities', $this->people->listPending('wecom'));
        $this->tpl->assign('companyUsers', $this->people->listUsers());
        return $this->tpl->display('openceo.desk');
    }

    public function post(array $params): Response
    {
        Auth::authOrRedirect([Roles::$owner, Roles::$admin, Roles::$manager], true);
        $action = $_POST['action'] ?? '';

        if ($action === 'save_profile') {
            $focus = preg_split('/[\\r\\n,，]+/u', (string) ($_POST['strategic_focus'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $this->company->saveProfile(
                name: (string) ($_POST['name'] ?? ''),
                background: (string) ($_POST['background'] ?? ''),
                businessModel: (string) ($_POST['business_model'] ?? ''),
                mission: (string) ($_POST['mission'] ?? ''),
                vision: (string) ($_POST['vision'] ?? ''),
                strategicFocus: array_values(array_map('trim', $focus)),
            );
        } elseif ($action === 'add_memory') {
            $content = trim((string) ($_POST['content'] ?? ''));
            if ($content !== '') {
                $this->company->addMemory(
                    content: $content,
                    memoryType: (string) ($_POST['memory_type'] ?? 'context'),
                    title: (string) ($_POST['title'] ?? ''),
                );
            }
        } elseif ($action === 'resolve_candidate') {
            $candidateId = (int) ($_POST['candidate_id'] ?? 0);
            $candidateAction = (string) ($_POST['candidate_action'] ?? 'ignore');
            $this->projects->resolveCandidate(
                candidateId: $candidateId,
                action: $candidateAction,
                projectId: (int) ($_POST['project_id'] ?? 0),
                name: (string) ($_POST['project_name'] ?? ''),
            );
        } elseif ($action === 'bind_identity') {
            $this->people->bindIdentity(
                identityId: (int) ($_POST['identity_id'] ?? 0),
                userId: (int) ($_POST['user_id'] ?? 0),
            );
        } elseif ($action === 'generate_output') {
            $outputType = (string) ($_POST['output_type'] ?? 'weekly_summary');
            $allowed = ['weekly_summary', 'monthly_summary', 'meeting_agenda', 'ppt_outline', 'video_narration'];
            if (! in_array($outputType, $allowed, true)) {
                throw new \InvalidArgumentException('Unsupported output type.');
            }
            $periodKey = match ($outputType) {
                'monthly_summary', 'ppt_outline', 'video_narration' => now()->format('Y-m'),
                default => now()->format('o-\WW'),
            };
            $this->agentGateway->generateOutput($outputType, $periodKey);
        } elseif ($action === 'supplement_monthly') {
            $outputId = (int) ($_POST['output_id'] ?? 0);
            $supplement = trim((string) ($_POST['supplement'] ?? ''));
            if ($outputId > 0 && $supplement !== '') {
                $this->outputs->supplement($outputId, $supplement);
            }
        } elseif ($action === 'finalize_monthly') {
            $outputId = (int) ($_POST['output_id'] ?? 0);
            if ($outputId > 0) {
                $this->outputs->finalize($outputId);
            }
        }

        return Frontcontroller::redirect(BASE_URL.'/openceo/desk');
    }
}
