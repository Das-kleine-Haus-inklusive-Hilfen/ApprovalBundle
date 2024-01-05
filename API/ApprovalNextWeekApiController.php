<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\API;

use App\API\BaseApiController;
use App\Repository\UserRepository;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'ApprovalNextWeekApi')]
final class ApprovalNextWeekApiController extends BaseApiController
{
    public function __construct(
        private ViewHandlerInterface $viewHandler,
        private UserRepository $userRepository,
        private ApprovalRepository $approvalRepository,
        private AuthorizationCheckerInterface $security,
        private TranslatorInterface $translator
    ) {
    }

    #[OA\Response(response: 200, description: 'Status of selected week')]
    #[Rest\QueryParam(name: 'user', requirements: '\d+', strict: true, nullable: true, description: 'User ID to get information for')]
    #[Route(methods: ['GET'], path: '/next-week')]
    #[ApiSecurity(name: 'apiUser')]
    #[ApiSecurity(name: 'apiToken')]
    public function nextWeekAction(Request $request): Response
    {
        $selectedUserId = $request->query->get('user');
        $currentUser = $this->getUser();

        if ($selectedUserId !== null) {
            if (!$this->isGrantedViewAllApproval() && !$this->isGrantedViewTeamApproval()) {
                return $this->error400($this->translator->trans('api.accessDenied'));
            }
            if (
                !$this->isGrantedViewAllApproval() &&
                $this->isGrantedViewTeamApproval() &&
                empty($this->checkIfUserInTeam($currentUser, $selectedUserId))
            ) {
                return $this->error400($this->translator->trans('api.wrongTeam'));
            }
            $selectedUser = $this->userRepository->find($selectedUserId);
            if (!$selectedUser || !$selectedUser->isEnabled()) {
                return $this->error404($this->translator->trans('api.wrongUser'));
            }
            $currentUser = $selectedUser;
        }

        $nextApproveWeek = $this->approvalRepository->getNextApproveWeek($currentUser);
        if ($nextApproveWeek) {
            return $this->viewHandler->handle(
                new View(
                    $this->translator->trans(
                        $nextApproveWeek
                    ),
                    200
                )
            );
        }

        return $this->error404($this->translator->trans('api.noData'));
    }

    private function isGrantedViewAllApproval(): bool
    {
        return $this->security->isGranted('view_all_approval');
    }

    private function isGrantedViewTeamApproval(): bool
    {
        return $this->security->isGranted('view_team_approval');
    }

    private function error404(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 404)
        );
    }

    private function error400(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 400)
        );
    }

    private function checkIfUserInTeam($user, $selectedUserId): array
    {
        return array_filter(
            $user->getTeams(),
            function ($team) use ($selectedUserId) {
                foreach ($team->getUsers() as $user) {
                    if ($user->getId() == $selectedUserId) {
                        return true;
                    }
                }

                return false;
            }
        );
    }
}
