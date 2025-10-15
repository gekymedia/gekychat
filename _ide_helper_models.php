<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $user_one_id
 * @property int $user_two_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $other_user
 * @property-read mixed $unread_count
 * @property-read \App\Models\Message|null $latestMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\User $userOne
 * @property-read \App\Models\User $userTwo
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation betweenUsers($user1, $user2)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereUserOneId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereUserTwoId($value)
 */
	class Conversation extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property int $owner_id
 * @property string|null $description
 * @property string|null $avatar_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $members
 * @property-read int|null $members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupMessage> $messages
 * @property-read int|null $messages_count
 * @property-read \App\Models\User $owner
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group whereAvatarPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Group whereUpdatedAt($value)
 */
	class Group extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $group_id
 * @property int $sender_id
 * @property string|null $body
 * @property int|null $reply_to_id
 * @property string|null $delivered_at
 * @property string|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Group $group
 * @property-read GroupMessage|null $replyTo
 * @property-read \App\Models\User $sender
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereDeliveredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereReplyToId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupMessage whereUpdatedAt($value)
 */
	class GroupMessage extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property string|null $body
 * @property int|null $reply_to
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MessageAttachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\Conversation $conversation
 * @property-read mixed $is_own
 * @property-read mixed $status
 * @property-read mixed $time_ago
 * @property-read Message|null $replyTo
 * @property-read \App\Models\User $sender
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message forUser($userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message unread()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereConversationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereDeliveredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReplyTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUpdatedAt($value)
 */
	class Message extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $message_id
 * @property string $file_path
 * @property string $file_type
 * @property int|null $file_size
 * @property string|null $original_name
 * @property string|null $dimensions
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $file_extension
 * @property-read mixed $human_readable_size
 * @property-read mixed $is_audio
 * @property-read mixed $is_document
 * @property-read mixed $is_image
 * @property-read mixed $is_pdf
 * @property-read mixed $is_video
 * @property-read mixed $url
 * @property-read \App\Models\Message $message
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereDimensions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereFileSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereFileType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MessageAttachment whereUpdatedAt($value)
 */
	class MessageAttachment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $avatar_path
 * @property string|null $about
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property string|null $two_factor_code
 * @property \Illuminate\Support\Carbon|null $two_factor_expires_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $phone
 * @property string|null $otp_code
 * @property \Illuminate\Support\Carbon|null $otp_expires_at
 * @property bool $is_admin
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Conversation> $conversationsAsUserOne
 * @property-read int|null $conversations_as_user_one_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Conversation> $conversationsAsUserTwo
 * @property-read int|null $conversations_as_user_two_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \NotificationChannels\WebPush\PushSubscription> $pushSubscriptions
 * @property-read int|null $push_subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $sentMessages
 * @property-read int|null $sent_messages_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAbout($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOtpCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOtpExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

