<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>McDonald's Reservation Form</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            margin: 0;
            background: #fff8e7;
        }

        .navbar {
            background: #d9230f;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            margin: 0;
            font-size: 28px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            font-weight: bold;
        }

        .hero {
            background: linear-gradient(rgba(217,35,15,0.85), rgba(255,193,7,0.85)),
                        url('https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=1400&q=80') center/cover no-repeat;
            padding: 70px 20px;
        }

        .container {
            max-width: 750px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.15);
        }

        h1 {
            color: #d9230f;
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, select, textarea, button {
            width: 100%;
            padding: 12px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        textarea {
            resize: vertical;
        }

        button {
            background: #d9230f;
            color: white;
            border: none;
            margin-top: 22px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #b71c0c;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 8px;
        }

        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .container {
                padding: 20px;
            }

            .hero {
                padding: 40px 15px;
            }
        }
    </style>
</head>
<body>

    <div class="navbar">
        <h1>McDonald's Reservations</h1>
        <div>
            <a href="/">Home</a>
            <a href="/reservations/create">Book Now</a>
        </div>
    </div>

    <div class="hero">
        <div class="container">
            <h1>Reservation Form</h1>

            @if(session('success'))
                <div class="success">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('reservations.store') }}" method="POST">
                @csrf

                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}">
                @error('name')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}">
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
                @error('phone')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="reservation_type">Reservation Type</label>
                <select id="reservation_type" name="reservation_type">
                    <option value="birthday" {{ old('reservation_type') == 'birthday' ? 'selected' : '' }}>Birthday Party</option>
                    <option value="business" {{ old('reservation_type') == 'business' ? 'selected' : '' }}>Business Reservation</option>
                </select>
                @error('reservation_type')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="branch">Branch</label>
                <input type="text" id="branch" name="branch" value="{{ old('branch') }}">
                @error('branch')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="event_date">Event Date</label>
                <input type="date" id="event_date" name="event_date" value="{{ old('event_date') }}">
                @error('event_date')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="event_time">Event Time</label>
                <input type="time" id="event_time" name="event_time" value="{{ old('event_time') }}">
                @error('event_time')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="guests">Number of Guests</label>
                <input type="number" id="guests" name="guests" value="{{ old('guests') }}">
                @error('guests')
                    <div class="error">{{ $message }}</div>
                @enderror

                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="4">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="error">{{ $message }}</div>
                @enderror

                <button type="submit">Submit Reservation</button>
            </form>
        </div>
    </div>

</body>
</html>