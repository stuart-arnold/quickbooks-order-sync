<!DOCTYPE html>
<html>
<head>
    <title>Fulfillment Test</title>
</head>
<body>
    <form method="POST" action="{{ route('fulfillment.run') }}">
        @csrf
        <button type="submit">Run Fulfillment</button>
    </form>

    @if ($results)
        <h2>Results</h2>
        <pre>{{ print_r($results, true) }}</pre>
    @endif
</body>
</html>