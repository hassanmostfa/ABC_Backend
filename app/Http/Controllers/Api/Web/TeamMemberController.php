<?php

namespace App\Http\Controllers\Api\Web;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\TeamMemberRepositoryInterface;
use App\Http\Resources\Web\WebTeamMemberResource;
use Illuminate\Http\JsonResponse;

class TeamMemberController extends BaseApiController
{
    protected $teamMemberRepository;

    public function __construct(TeamMemberRepositoryInterface $teamMemberRepository)
    {
        $this->teamMemberRepository = $teamMemberRepository;
    }

    /**
     * Get all team members grouped by level
     */
    public function getAll(): JsonResponse
    {
        try {
            $teamMembers = $this->teamMemberRepository->getAll();
            
            // Group team members by level
            $grouped = $teamMembers->groupBy('level')->map(function ($members, $level) {
                return [
                    'level' => $level,
                    'title' => $this->getLevelTitle($level),
                    'members' => WebTeamMemberResource::collection($members)
                ];
            })->values();
            
            return $this->successResponse(
                $grouped,
                'Team members retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving team members: ' . $e->getMessage());
        }
    }

    /**
     * Get title for a level
     */
    private function getLevelTitle(string $level): string
    {
        // Map level values to titles
        $levelTitles = [
            '1' => 'Level 1',
            '2' => 'Level 2',
            '3' => 'Level 3',
            '4' => 'Level 4',
        ];

        return $levelTitles[$level] ?? $level;
    }
}

