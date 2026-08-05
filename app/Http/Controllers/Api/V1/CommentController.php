<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommentRequest;
use App\Http\Requests\Community\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use ApiResponse;

    /**
     * List comments for a post.
     */
    public function index(Post $post): JsonResponse
    {
        $comments = $post->comments()->with('user')->orderBy('created_at', 'asc')->get();

        return $this->success(CommentResource::collection($comments));
    }

    /**
     * Add a comment to a post.
     */
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->content,
        ]);

        $comment->load('user');

        return $this->success(new CommentResource($comment), 'Comment added successfully.', 201);
    }

    /**
     * Update the authenticated user's own comment.
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);

        $comment->update($request->validated());

        $comment->load('user');

        return $this->success(new CommentResource($comment), 'Comment updated successfully.');
    }

    /**
     * Delete the user's own comment (or any comment for admins).
     */
    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return $this->success(null, 'Comment deleted successfully.', 204);
    }
}
