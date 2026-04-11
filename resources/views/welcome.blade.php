<!DOCTYPE html>
<html>
<head>
    <title>Magic Login Link</title>
</head>
<body>
    <h1>Hello {{ $user->name }},</h1>
    <p>Click the link below to log in to your supplier dashboard. This link expires in 30 minutes.</p>
    <p>
        <a href="{{ $url }}" style="padding: 10px 20px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px;">
            Log In Now
        </a>
    </p>
    <p>If the button doesn't work, copy and paste this URL into your browser:</p>
    <p>{{ $url }}</p>
    <p>Thank you,<br>{{ config('app.name') }}</p>
</body>
</html>