<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <ul>
            <li>
                <a href="{{ $latestFeedUrl }}" target="_blank" rel="noopener noreferrer">Latest Products</a>
                <div>{{ $latestFeedUrl }}</div>
            </li>
            <li>
                <a href="{{ $featuredFeedUrl }}" target="_blank" rel="noopener noreferrer">Featured Products</a>
                <div>{{ $featuredFeedUrl }}</div>
            </li>
            @foreach ($categories as $category)
                <li>
                    <a href="{{ $category['feed_url'] }}" target="_blank" rel="noopener noreferrer">{{ $category['name'] }}</a>
                    <div>{{ $category['feed_url'] }}</div>
                </li>
            @endforeach
        </ul>
    </main>
</body>
</html>
