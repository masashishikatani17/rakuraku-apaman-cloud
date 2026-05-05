<?php

namespace App\Http\Middleware;

use App\Models\Book;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBookIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        $book = $this->resolveBook($request);

        if ($book !== null && $book->status === 'closed') {
            return redirect()
                ->back()
                ->withErrors([
                    'book_id' => 'この帳簿は年度締め済みです。登録・修正・削除を行う場合は、先に年度締め・帳簿ロック画面で再開してください。',
                ])
                ->withInput();
        }

        return $next($request);
    }

    private function isExemptRoute(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        return str_starts_with($routeName, 'closing.book-locks.');
    }

    private function resolveBook(Request $request): ?Book
    {
        $bookId = $request->input('book_id')
            ?: $request->input('target_book_id');

        if ($bookId !== null && $bookId !== '') {
            return Book::query()->find((int) $bookId);
        }

        $route = $request->route();

        if ($route === null) {
            return null;
        }

        foreach ($route->parameters() as $parameter) {
            if ($parameter instanceof Book) {
                return $parameter;
            }

            if ($parameter instanceof Model && isset($parameter->book_id)) {
                return Book::query()->find((int) $parameter->book_id);
            }
        }

        return null;
    }
}