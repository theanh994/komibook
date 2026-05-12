<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookAnnotation;
use Illuminate\Http\Request;

class BookAnnotationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $query = BookAnnotation::where('user_id', $userId)->with('book');

        if ($request->has('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
            'type' => 'required|in:highlight,note,bookmark',
            'chapter' => 'nullable|string',
            'highlighted_text' => 'nullable|string',
            'note_content' => 'nullable|string',
            'color' => 'nullable|string',
            'page_number' => 'nullable|integer',
        ]);

        $annotation = BookAnnotation::create([
            'user_id' => $request->user()->id,
            'book_id' => $request->book_id,
            'chapter' => $request->chapter,
            'highlighted_text' => $request->highlighted_text,
            'note_content' => $request->note_content,
            'type' => $request->type,
            'color' => $request->color,
            'page_number' => $request->page_number,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Annotation saved.',
            'data' => $annotation
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $annotation = BookAnnotation::where('user_id', $request->user()->id)->findOrFail($id);
        
        $request->validate([
            'note_content' => 'nullable|string',
            'color' => 'nullable|string',
        ]);

        $annotation->update($request->only(['note_content', 'color']));

        return response()->json([
            'status' => 'success',
            'message' => 'Annotation updated.',
            'data' => $annotation
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $annotation = BookAnnotation::where('user_id', $request->user()->id)->findOrFail($id);
        $annotation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Annotation deleted.'
        ]);
    }

    public function recent(Request $request, $bookId)
    {
        $userId = $request->user()->id;
        $annotations = BookAnnotation::where('user_id', $userId)
            ->where('book_id', $bookId)
            ->latest()
            ->limit(3)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $annotations
        ]);
    }
}
