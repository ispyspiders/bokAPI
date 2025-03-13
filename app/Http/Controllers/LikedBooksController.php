<?php

namespace App\Http\Controllers;

use App\Models\LikedBook;
use Illuminate\Http\Request;
use App\Models\User;

class LikedBooksController extends Controller
{
    // Lägg till bok i gillade böcker
    public function addToLikedBooks(Request $request)
    {
        // Hämta autentiserad användare
        $user = $request->user(); 

        $request->validate([
            'book_id' => 'required|string'
        ]);

        // Kontrollera om bok redan är gillad
        if (LikedBook::where('user_id', $user->id)->where('book_id', $request->book_id)->exists()) {
            return response()->json([
                'message' => 'Boken finns redan i dina gillade böcker'
            ], 400);
        }

        // Skapa gillad bok
        $likedBook = LikedBook::create([
            'user_id' => $user->id,
            'book_id' => $request->book_id
        ]);

        // Returnera gillad bok - 201 created
        return response()->json([
            'message' => 'Boken har lagts till i dina gillade böcker',
            'data' => $likedBook
        ], 201);
    }

    // Ta bort bok från gillade böcker
    public function removeFromLikedBooks(Request $request, $bookId)
    {
        // Hämta autentiserad användare
        $user = $request->user();

        // Kontrollera om bok finns i användarens lista
        $likedBook = LikedBook::where('user_id', $user->id)->where('book_id', $bookId)->first();

        if(!$likedBook) {
            return response()->json([
                'message' => 'Boken finns inte bland dina gillade böcker'
            ], 404);
        }

        // Ta bort boken
        $likedBook->delete();

        return response()->json([
            'message' => 'Boken har tagits bort från dina gillade böcker'
        ]);
    }

    // Hämta alla gillade böcker för en användare
    public function getLikedBooks(string $id){

        // hitta användare
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Ingen användare med id' + $id + 'hittades'
            ], 404);
        }

        $likedBooks = $user->likedBooks;

        return response()->json($likedBooks);
    }

    public function getNumberOfLikes($bookId){
       $likesCount = LikedBook::where('book_id', $bookId)->count();

       return response()->json(['likes_count' => $likesCount]);
    }
}
