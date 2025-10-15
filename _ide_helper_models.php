<?php
// @formatter:off

/**
 * A helper file for Laravel IDE Helper
 *
 * This file is not included in the git repository and is ignored by git.
 */

namespace {
    exit("This file should not be included, only analyzed by your IDE");
}

namespace App\Models {
    /**
     * @property int $id
     * @property int $music_arranger_id
     * @property int $song_id
     * @property int|null $proposed_singer_id
     * @property string|null $arrangement_notes
     * @property \Carbon\Carbon|null $requested_date
     * @property string $current_state
     * @property int|null $approved_singer_id
     * @property string|null $producer_notes
     * @property string|null $final_approval_notes
     * @property string|null $script_content
     * @property array|null $storyboard_data
     * @property \Carbon\Carbon|null $recording_schedule
     * @property \Carbon\Carbon|null $shooting_schedule
     * @property string|null $shooting_location
     * @property array|null $budget_data
     * @property string|null $arrangement_file_path
     * @property string|null $arrangement_file_url
     * @property string|null $processed_audio_path
     * @property string|null $processed_audio_url
     * @property string|null $sound_engineering_notes
     * @property string|null $quality_control_notes
     * @property bool $quality_control_approved
     * @property string $submission_status
     * @property string|null $producer_feedback
     * @property \Carbon\Carbon|null $submitted_at
     * @property \Carbon\Carbon|null $approved_at
     * @property \Carbon\Carbon|null $rejected_at
     * @property \Carbon\Carbon|null $completed_at
     * @property int $version
     * @property int|null $parent_submission_id
     * @property \Carbon\Carbon|null $created_at
     * @property \Carbon\Carbon|null $updated_at
     * @property-read \App\Models\User $musicArranger
     * @property-read \App\Models\Song $song
     * @property-read \App\Models\User|null $proposedSinger
     * @property-read \App\Models\User|null $approvedSinger
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\MusicWorkflowState[] $workflowStates
     * @property-read \App\Models\MusicWorkflowState|null $currentWorkflowState
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\MusicWorkflowHistory[] $workflowHistory
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\MusicWorkflowNotification[] $notifications
     * @property-read string $status_color
     * @property-read string $status_label
     * @property-read string $priority
     *
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission byMusicArranger($musicArrangerId)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission byRole($role)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission byState($state)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission pending()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission query()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicSubmission urgent()
     * @method bool canTransitionTo($newState)
     */
    class MusicSubmission extends \Illuminate\Database\Eloquent\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property int $submission_id
     * @property string $current_state
     * @property string $assigned_to_role
     * @property int|null $assigned_to_user_id
     * @property string|null $notes
     * @property \Carbon\Carbon|null $created_at
     * @property \Carbon\Carbon|null $updated_at
     * @property-read \App\Models\MusicSubmission $submission
     * @property-read \App\Models\User|null $assignedUser
     * @property-read string $state_color
     * @property-read string $state_label
     *
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowState byRole($role)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowState bySubmission($submissionId)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowState byUser($userId)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowState newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowState newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowState query()
     */
    class MusicWorkflowState extends \Illuminate\Database\Eloquent\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property int $submission_id
     * @property string|null $from_state
     * @property string $to_state
     * @property int $action_by_user_id
     * @property string|null $action_notes
     * @property \Carbon\Carbon|null $created_at
     * @property \Carbon\Carbon|null $updated_at
     * @property-read \App\Models\MusicSubmission $submission
     * @property-read \App\Models\User $actionByUser
     * @property-read string $transition_label
     * @property-read string $action_description
     *
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowHistory bySubmission($submissionId)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowHistory byTransition($fromState, $toState)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowHistory byUser($userId)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowHistory newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowHistory newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowHistory query()
     */
    class MusicWorkflowHistory extends \Illuminate\Database\Eloquent\Model {}
}

namespace App\Models {
    /**
     * @property int $id
     * @property int $submission_id
     * @property int $user_id
     * @property string $notification_type
     * @property string $title
     * @property string $message
     * @property bool $is_read
     * @property \Carbon\Carbon|null $read_at
     * @property \Carbon\Carbon|null $created_at
     * @property \Carbon\Carbon|null $updated_at
     * @property-read \App\Models\MusicSubmission $submission
     * @property-read \App\Models\User $user
     * @property-read string $icon
     * @property-read string $color
     * @property-read string $time_ago
     * @property-read array $workflow_data
     *
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification bySubmission($submissionId)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification byType($type)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification byUser($userId)
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification newModelQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification newQuery()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification read()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification query()
     * @method static \Illuminate\Database\Eloquent\Builder|MusicWorkflowNotification unread()
     * @method void markAsRead()
     */
    class MusicWorkflowNotification extends \Illuminate\Database\Eloquent\Model {}
}

namespace App\Http\Controllers {
    /**
     * @method \Illuminate\Http\JsonResponse getSubmissions(\Illuminate\Http\Request $request)
     * @method \Illuminate\Http\JsonResponse getSubmission($id)
     * @method \Illuminate\Http\JsonResponse updateSubmission(\Illuminate\Http\Request $request, $id)
     * @method \Illuminate\Http\JsonResponse deleteSubmission($id)
     * @method \Illuminate\Http\JsonResponse submitSubmission($id)
     * @method \Illuminate\Http\JsonResponse cancelSubmission($id)
     * @method \Illuminate\Http\JsonResponse resubmitSubmission(\Illuminate\Http\Request $request, $id)
     * @method \Illuminate\Http\JsonResponse downloadFiles($id)
     * @method array getAvailableActions($submission)
     */
    class MusicArrangerHistoryController extends \App\Http\Controllers\Controller {}
}























