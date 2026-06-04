@extends('layouts.frontend')

@section('title', 'Posts - '.config('app.name', 'Laravel'))
@section('main_class', 'mx-auto w-full max-w-6xl flex-1 px-6 py-10 sm:py-14')

@section('content')
    <section class="space-y-8">
        <header class="max-w-2xl space-y-3">
            <p class="text-sm font-medium uppercase tracking-wide text-muted">
                Blog
            </p>
            <h1 class="text-3xl font-semibold tracking-tight text-highlighted sm:text-4xl">
                Latest Posts
            </h1>
            <p class="text-base leading-7 text-muted">
                Read the newest articles, notes, and updates from {{ config('app.name', 'Laravel') }}.
            </p>
        </header>

        @if ($posts->isEmpty())
            <div class="rounded-lg border border-default bg-default p-10 text-center">
                <h2 class="text-lg font-semibold text-highlighted">
                    No posts yet
                </h2>
                <p class="mt-2 text-sm text-muted">
                    Published articles will appear here.
                </p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <article class="overflow-hidden rounded-lg border border-default bg-default transition hover:-translate-y-0.5 hover:shadow-sm">
                        <a href="{{ route('read', $post->slug) }}" class="block">
                            @if ($imageUrls[$post->id] ?? null)
                                <img
                                    src="{{ $imageUrls[$post->id] }}"
                                    alt="{{ $post->image_caption ?? $post->title }}"
                                    class="aspect-video w-full object-cover"
                                >
                            @else
                                <div class="flex aspect-video w-full items-center justify-center bg-muted text-sm text-muted">
                                    No image
                                </div>
                            @endif
                        </a>

                        <div class="space-y-4 p-5">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                                @if ($post->published_at)
                                    <time datetime="{{ $post->published_at->toIso8601String() }}">
                                        {{ $post->published_at->translatedFormat('d F Y') }}
                                    </time>
                                @else
                                    <span>Draft</span>
                                @endif

                                @if ($post->user)
                                    <span aria-hidden="true">/</span>
                                    <span>{{ $post->user->name }}</span>
                                @endif
                            </div>

                            <div class="space-y-2">
                                <h2 class="line-clamp-2 text-lg font-semibold leading-6 text-highlighted">
                                    <a href="{{ route('read', $post->slug) }}" class="hover:underline">
                                        {{ $post->title }}
                                    </a>
                                </h2>

                                <p class="line-clamp-3 text-sm leading-6 text-muted">
                                    {{ $post->excerpt ?? str($post->content)->stripTags()->limit(140) }}
                                </p>
                            </div>

                            @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($post->categories->take(2) as $category)
                                        <span class="rounded-md bg-muted px-2 py-1 text-xs text-muted">
                                            {{ $category->name }}
                                        </span>
                                    @endforeach

                                    @foreach ($post->tags->take(2) as $tag)
                                        <span class="rounded-md border border-default px-2 py-1 text-xs text-muted">
                                            #{{ $tag->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <a href="{{ route('read', $post->slug) }}" class="inline-flex text-sm font-medium text-highlighted hover:underline">
                                Read post
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div>
                {{ $posts->links() }}
            </div>
        @endif
    </section>
@endsection
