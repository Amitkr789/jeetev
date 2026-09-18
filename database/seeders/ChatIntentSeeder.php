<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChatIntentSeeder extends Seeder
{
    public function run(): void
    {

        DB::table('chatbot_intents')->truncate();

        DB::table('chatbot_intents')->insert([

            [
                'intent'=>'greeting',

                'keywords'=>json_encode([
                    'hi',
                    'hello',
                    'hii',
                    'hey',
                    'namaste',
                    'good morning',
                    'good evening'
                ]),

                'responses'=>json_encode([
                    'Hello 👋 Welcome to Jojo EV. How may I help you today?',
                    'Hi 👋 Thank you for contacting Jojo EV. How can I help you?',
                    'Namaste 🙏 Jojo EV mein aapka swagat hai. Main aapki kis tarah madad kar sakta hoon?'
                ]),

                'priority'=>1
            ],

            [
                'intent'=>'ad',

                'keywords'=>json_encode([
                    'ad',
                    'advertisement',
                    'facebook',
                    'instagram',
                    'youtube',
                    'google'
                ]),

                'responses'=>json_encode([
                    'Thank you for your interest 😊 Which EV product are you looking for?',
                    'Glad you contacted us. Please tell us which product you want.'
                ]),

                'priority'=>2
            ],

            [
                'intent'=>'thanks',

                'keywords'=>json_encode([
                    'thanks',
                    'thank you',
                    'ok',
                    'okay'
                ]),

                'responses' => json_encode([
                    "You're welcome 😊",
                    "Happy to help.",
                    "Thank you."
                ]),

                'priority'=>3,
            ],

        ]);

    }
}
