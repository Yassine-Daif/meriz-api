<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\GradeSubmission;
use App\Http\Requests\Submissions\GradeSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Submission;
use Illuminate\Support\Facades\Gate;

class SubmissionGradeController extends Controller
{
    public function store(GradeSubmissionRequest $request, Submission $submission, GradeSubmission $grading): SubmissionResource
    {
        Gate::authorize('grade', $submission);

        $grading->grade($submission, $request->validated('grade'), $request->validated('feedback'));

        return $this->resource($submission);
    }

    /**
     * Retire la note : l'élève peut de nouveau modifier son rendu.
     */
    public function destroy(Submission $submission, GradeSubmission $grading): SubmissionResource
    {
        Gate::authorize('grade', $submission);

        $grading->remove($submission);

        return $this->resource($submission);
    }

    private function resource(Submission $submission): SubmissionResource
    {
        $submission->loadMissing(['student', 'assignment']);

        return (new SubmissionResource($submission))->detailed();
    }
}
