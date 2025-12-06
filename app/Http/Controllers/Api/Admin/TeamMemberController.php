<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\TeamMemberRepositoryInterface;
use App\Http\Resources\Admin\TeamMemberResource;
use App\Http\Requests\Admin\TeamMemberRequest;
use App\Traits\ManagesFileUploads;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeamMemberController extends BaseApiController
{
    use ManagesFileUploads;
    
    protected $teamMemberRepository;

    public function __construct(TeamMemberRepositoryInterface $teamMemberRepository)
    {
        $this->teamMemberRepository = $teamMemberRepository;
    }

    /**
     * Display a listing of the team members with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'level' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'level' => $request->input('level'),
            'job_title' => $request->input('job_title'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $teamMembers = $this->teamMemberRepository->getAllPaginated($filters, $perPage);

        // Transform data using TeamMemberResource
        $transformedTeamMembers = TeamMemberResource::collection($teamMembers->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Team members retrieved successfully',
            'data' => $transformedTeamMembers,
            'pagination' => [
                'current_page' => $teamMembers->currentPage(),
                'last_page' => $teamMembers->lastPage(),
                'per_page' => $teamMembers->perPage(),
                'total' => $teamMembers->total(),
                'from' => $teamMembers->firstItem(),
                'to' => $teamMembers->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created team member in storage.
     */
    public function store(TeamMemberRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $this->uploadFile($request->file('image'), TeamMember::$STORAGE_DIR, 'public');
            $validatedData['image'] = $imagePath;
        }
        
        $teamMember = $this->teamMemberRepository->create($validatedData);
        $transformedTeamMember = new TeamMemberResource($teamMember);

        // Log activity
        logAdminActivity('created', 'TeamMember', $teamMember->id);

        return $this->createdResponse($transformedTeamMember, 'Team member created successfully');
    }

    /**
     * Display the specified team member.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $teamMember = $this->teamMemberRepository->findById($id);

        if (!$teamMember) {
            return $this->notFoundResponse('Team member not found');
        }

        // Transform data using TeamMemberResource
        $transformedTeamMember = new TeamMemberResource($teamMember);

        return $this->resourceResponse($transformedTeamMember, 'Team member retrieved successfully');
    }

    /**
     * Update the specified team member in storage.
     */
    public function update(TeamMemberRequest $request, int $id): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Get the existing team member to check for old image
        $existingTeamMember = $this->teamMemberRepository->findById($id);
        
        if (!$existingTeamMember) {
            return $this->notFoundResponse('Team member not found');
        }
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($existingTeamMember->image) {
                $this->deleteFile($existingTeamMember->image, 'public');
            }
            
            $imagePath = $this->uploadFile($request->file('image'), TeamMember::$STORAGE_DIR, 'public');
            $validatedData['image'] = $imagePath;
        }
        
        $teamMember = $this->teamMemberRepository->update($id, $validatedData);

        if (!$teamMember) {
            return $this->notFoundResponse('Team member not found');
        }

        $transformedTeamMember = new TeamMemberResource($teamMember);
        
        // Log activity
        logAdminActivity('updated', 'TeamMember', $teamMember->id);
        
        return $this->updatedResponse($transformedTeamMember, 'Team member updated successfully');
    }

    /**
     * Remove the specified team member from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        // Get the team member to check for image before deletion
        $teamMember = $this->teamMemberRepository->findById($id);
        
        if (!$teamMember) {
            return $this->notFoundResponse('Team member not found');
        }
        
        // Delete the image if it exists
        if ($teamMember->image) {
            $this->deleteFile($teamMember->image, 'public');
        }
        
        $deleted = $this->teamMemberRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Team member not found');
        }

        // Log activity
        logAdminActivity('deleted', 'TeamMember', $id);

        return $this->deletedResponse('Team member deleted successfully');
    }
}

