<?php

namespace App\Trait;

use App\Models\Log as ModelsLog;
use Illuminate\Http\Request;
use Symfony\Component\Uid\Ulid;

trait Log
{
    private ModelsLog $log;

    public function initLog(Request $request)
    {
        $this->log = new ModelsLog;
        $this->log->id = Ulid::generate();
        $this->log->user_agent = $request->header('User-Agent');
        $this->log->ip = $request->ip();
        $this->log->method = $request->method();
        $this->log->action = $request->url();
        $this->log->user = (auth()->user() ? auth()->user()->id : null);
    }

    public function setUser(string $user) : void
    {
        $this->log->user = $user;
    }

    public function setBefore(string $before) : void
    {
        $this->log->before = $this->privateData($before);
    }

    public function setAfter(string $after) : void
    {
        $this->log->after = $this->privateData($after);
    }

    public function setBody(string $body) : void
    {
        $this->log->body = $this->privateData($body);
    }

    public function setDescription(string $description) : void
    {
        $this->log->description = $description;
    }

    public function saveLog() : void
    {
        $this->log->save();
    }

    private function privateData(string|array $data): string|array
    {
        $json = null;

        if (is_string($data))
        {
            $json = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE)
            {
                return $data;
            }
        }
        else
        {
            $json = $data;
        }

        $keysToPrivate = [
            'password',
        ];

        foreach ($json as $key => $value)
        {
            if (is_array($value))
            {
                $json[$key] = $this->privateData($value);
            }
            else
            {
                if (in_array($key, $keysToPrivate))
                {
                    $json[$key] = "[PRIVATED]";
                }
            }
        }

        return (is_array($data) ? $json : json_encode($json));
    }
}
