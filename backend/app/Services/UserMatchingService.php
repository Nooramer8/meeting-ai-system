<?php

namespace App\Services;

use App\Models\User;

class UserMatchingService
{
    public function match(?string $name, ?string $email): ?User
    {
        if ($email) {
            $user = User::whereRaw('lower(email) = ?', [strtolower($email)])->first();
            if ($user) {
                return $user;
            }
        }

        if ($name) {
            return User::whereRaw('lower(name) = ?', [strtolower($name)])->first();
        }

        return null;
    }

    public function matchForTask(?string $name, ?string $email, ?string $title, ?string $description): ?User
    {
        $direct = $this->match($name, $email);
        if ($direct) {
            return $direct;
        }

        $text = mb_strtolower(trim(($title ?? '').' '.($description ?? '')));
        if ($text === '') {
            return null;
        }

        $bestUser = null;
        $bestScore = 0;

        foreach (User::query()->select('id', 'name', 'email', 'position')->get() as $user) {
            $score = 0;

            foreach ([$user->name, $user->email, $user->position] as $value) {
                $value = mb_strtolower(trim((string) $value));
                if ($value !== '' && str_contains($text, $value)) {
                    $score += $value === mb_strtolower((string) $user->position) ? 3 : 2;
                }
            }

            foreach (preg_split('/[\s,،\/\-\|]+/u', (string) $user->position, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                $token = mb_strtolower(trim($token));
                if (mb_strlen($token) >= 3 && str_contains($text, $token)) {
                    $score += 1;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestUser = $user;
            }
        }

        return $bestScore > 0 ? $bestUser : null;
    }
}
