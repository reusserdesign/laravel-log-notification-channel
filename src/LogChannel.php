<?php

namespace NotificationChannels\Log;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use NotificationChannels\Log\Exceptions\CouldNotSendNotification;

class LogChannel
{
    /**
     * @var Dispatcher
     */
    protected $events;

    /**
     * LogChannel constructor.
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     *
     * @throws \NotificationChannels\Log\Exceptions\CouldNotSendNotification
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            $logChannel = $this->getLogChannel();

            if (!$logChannel) {
                throw CouldNotSendNotification::invalidLogChannel($logChannel);
            }

            $message = $notification->toLog($notifiable);

            if (!$message instanceof LogMessage) {
                throw CouldNotSendNotification::invalidMessageObject($message);
            }

            $level = $this->getLogLevel();

            $logger = Log::channel($logChannel);

            if (method_exists($logger, $level)) {
                $logger->$level($message->content);
            } else {
                $logger->debug($message->content);
            }
        } catch (Exception $exception) {
            $event = new NotificationFailed($notifiable, $notification, 'log', ['message' => $exception->getMessage(), 'exception' => $exception]);
            if (function_exists('event')) { // Use event helper when possible to add Lumen support
                event($event);
            } else {
                $this->events->fire($event);
            }
        }
    }

    /**
     * @return string
     */
    private function getLogChannel()
    {
        return config('log-notification-channel.channel') ?? config('logging.default');
    }

    /**
     * @return string
     */
    private function getLogLevel()
    {
        return config('log-notification-channel.level') ?? 'debug';
    }
}
