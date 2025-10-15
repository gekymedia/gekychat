<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This migration augments the existing `messages` table with additional
 * columns required for full chat functionality. The initial schema only
 * stored the sender, conversation and message body. However, the chat
 * controllers and models expect support for replies, forwards, encryption,
 * expiring messages and per‑message status timestamps. Without these
 * fields Laravel will attempt to insert unknown columns and MySQL will
 * raise an error. Running this migration will add the missing columns
 * whilst remaining backwards compatible by marking them nullable. If you
 * have existing data the default values will be preserved and existing
 * messages will continue to work.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Reply chain: points to another message in the same table
            if (!Schema::hasColumn('messages', 'reply_to')) {
                $table->unsignedBigInteger('reply_to')->nullable()->after('body');
                $table->foreign('reply_to')->references('id')->on('messages')->nullOnDelete();
            }

            // Forward chain: reference to the original message
            if (!Schema::hasColumn('messages', 'forwarded_from_id')) {
                $table->unsignedBigInteger('forwarded_from_id')->nullable()->after('reply_to');
                $table->foreign('forwarded_from_id')->references('id')->on('messages')->nullOnDelete();
            }

            // JSON column to hold an arbitrary forward chain (for multi‑level forwards)
            if (!Schema::hasColumn('messages', 'forward_chain')) {
                $table->json('forward_chain')->nullable()->after('forwarded_from_id');
            }

            // Encryption flag (client side may encrypt message body)
            if (!Schema::hasColumn('messages', 'is_encrypted')) {
                $table->boolean('is_encrypted')->default(false)->after('forward_chain');
            }

            // Optional expiry for disappearing messages
            if (!Schema::hasColumn('messages', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('is_encrypted');
            }

            // When the message was delivered to at least one other recipient
            if (!Schema::hasColumn('messages', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('expires_at');
            }

            // When the message was read globally (for simple UIs)
            if (!Schema::hasColumn('messages', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('delivered_at');
            }

            // Support idempotent sends by clients (e.g. mobile) – new column
            if (!Schema::hasColumn('messages', 'client_uuid')) {
                $table->uuid('client_uuid')->nullable()->after('id')->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'client_uuid')) {
                $table->dropColumn('client_uuid');
            }
            if (Schema::hasColumn('messages', 'reply_to')) {
                $table->dropForeign(['reply_to']);
                $table->dropColumn('reply_to');
            }
            if (Schema::hasColumn('messages', 'forwarded_from_id')) {
                $table->dropForeign(['forwarded_from_id']);
                $table->dropColumn('forwarded_from_id');
            }
            if (Schema::hasColumn('messages', 'forward_chain')) {
                $table->dropColumn('forward_chain');
            }
            if (Schema::hasColumn('messages', 'is_encrypted')) {
                $table->dropColumn('is_encrypted');
            }
            if (Schema::hasColumn('messages', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
            if (Schema::hasColumn('messages', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
            if (Schema::hasColumn('messages', 'read_at')) {
                $table->dropColumn('read_at');
            }
        });
    }
};