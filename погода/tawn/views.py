import requests
import json
from datetime import datetime, timedelta, timezone, UTC

from django.shortcuts import render
from django.http import HttpResponse

def index(request):
    if request.method == "POST":
        city = str(request.POST.get('city'))
        api_key = "93f5d5316569bad8e3d8cab2733eaa55"

        # Текущая погода
        weather_url = f"https://api.openweathermap.org/data/2.5/weather?q={city}&appid={api_key}&units=metric&lang=ru"
        weather_data = requests.get(weather_url).json()

        try:
            if weather_data['cod'] == '404':
                return HttpResponse('{"status": "notfound"}')
            else:
                # Текущая погода
                city_name = weather_data['name']
                country = weather_data.get('sys').get('country', '-')
                ts = weather_data['dt']
                tzone = weather_data['timezone']
                date_time = datetime.fromtimestamp(ts, tz=timezone(timedelta(seconds=tzone))).strftime('%Y-%m-%d')
                temp = int(weather_data['main']['temp'])
                temp_F = format((temp * 1.8) + 32, '.1f')
                description = weather_data['weather'][0]['description']
                humidity = weather_data['main']['humidity']
                feels_like = int(weather_data['main']['feels_like'])
                wind = format(weather_data['wind']['speed'] * 3.6, '.1f')
                visibility = format(weather_data['visibility'] / 1000, '.2f')

                # Прогноз на завтра
                forecast_url = f"https://api.openweathermap.org/data/2.5/forecast?q={city}&appid={api_key}&units=metric&lang=ru"
                forecast_data = requests.get(forecast_url).json()

                tomorrow = datetime.now(UTC) + timedelta(days=1)
                tomorrow_date_str = tomorrow.strftime('%Y-%m-%d')

                tomorrow_forecast = None
                for item in forecast_data['list']:
                    if item['dt_txt'].startswith(tomorrow_date_str) and '12:00:00' in item['dt_txt']:
                        tomorrow_forecast = {
                            'temp': int(item['main']['temp']),
                            'description': item['weather'][0]['description'],
                            'humidity': item['main']['humidity'],
                            'feels_like': int(item['main']['feels_like']),
                            'wind': format(item['wind']['speed'] * 3.6, '.1f'),
                            'visibility': format(item.get('visibility', 10000) / 1000, '.2f'),
                            'time': item['dt_txt']
                        }
                        break

                context = {
                    'status': 'success',
                    'city': city_name,
                    'country': country,
                    'date_time': date_time,
                    'temp': temp,
                    'temp_F': temp_F,
                    'description': description,
                    'humidity': humidity,
                    'feels_like': feels_like,
                    'wind': wind,
                    'visibility': visibility,
                    'forecast': tomorrow_forecast
                }

                return HttpResponse(json.dumps(context))
        except Exception as e:
            return HttpResponse(json.dumps({'status': 'error', 'message': str(e)}))

    return render(request, 'index.html')
