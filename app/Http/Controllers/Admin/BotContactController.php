<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BotContactController extends Controller
{
    /**
     * Display list of bot contacts
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $bots = BotContact::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.bot-contacts.index', [
            'bots' => $bots,
        ]);
    }

    /**
     * Show form to create a new bot
     */
    public function create()
    {
        return view('admin.bot-contacts.create');
    }

    /**
     * Store a new bot contact
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();
        try {
            // Generate bot number and code
            $botNumber = BotContact::generateNextBotNumber();
            $code = BotContact::generateCode();

            // Create bot contact
            $bot = BotContact::create([
                'bot_number' => $botNumber,
                'name' => $request->name,
                'code' => $code,
                'description' => $request->description,
                'is_active' => true,
            ]);

            // Create or update associated user
            $bot->getOrCreateUser();

            DB::commit();

            return redirect()->route('admin.bot-contacts.index')
                ->with('success', "Bot created successfully! Bot Number: {$botNumber}, Code: {$code}");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create bot: ' . $e->getMessage()]);
        }
    }

    /**
     * Show bot details
     */
    public function show(BotContact $botContact)
    {
        $user = request()->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        return view('admin.bot-contacts.show', [
            'bot' => $botContact,
        ]);
    }

    /**
     * Show form to edit bot
     */
    public function edit(BotContact $botContact)
    {
        $user = request()->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        return view('admin.bot-contacts.edit', [
            'bot' => $botContact,
        ]);
    }

    /**
     * Update bot contact
     */
    public function update(Request $request, BotContact $botContact)
    {
        $user = $request->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $botContact->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : $botContact->is_active,
        ]);

        // Update associated user name
        $user = $botContact->getOrCreateUser();
        $user->update(['name' => $request->name]);

        return redirect()->route('admin.bot-contacts.index')
            ->with('success', 'Bot updated successfully');
    }

    /**
     * Regenerate bot code
     */
    public function regenerateCode(BotContact $botContact)
    {
        $user = request()->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        $newCode = BotContact::generateCode();
        $botContact->update(['code' => $newCode]);

        return redirect()->back()
            ->with('success', "New code generated: {$newCode}");
    }

    /**
     * Delete bot contact
     */
    public function destroy(BotContact $botContact)
    {
        $user = request()->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }

        // Don't allow deleting the default GekyBot (0000000000)
        if ($botContact->bot_number === '0000000000') {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot delete the default GekyBot']);
        }

        $botContact->delete();

        return redirect()->route('admin.bot-contacts.index')
            ->with('success', 'Bot deleted successfully');
    }
}
