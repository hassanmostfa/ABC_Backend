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
     * Get all team members
     */
    public function getAll(): JsonResponse
    {
        try {
            $teamMembers = $this->teamMemberRepository->getAll();
            
            return $this->collectionResponse(
                WebTeamMemberResource::collection($teamMembers),
                'Team members retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->serverErrorResponse('An error occurred while retrieving team members' . $e->getMessage());
        }
    }
}

