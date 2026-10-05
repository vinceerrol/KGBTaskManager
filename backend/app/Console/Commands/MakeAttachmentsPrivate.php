<?php

namespace App\Console\Commands;

use App\Models\TaskAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MakeAttachmentsPrivate extends Command
{
    protected $signature = 'attachments:make-private';

    protected $description = 'Move attachments uploaded before private storage from the public disk to the private disk';

    public function handle(): int
    {
        $moved = 0;
        $missing = 0;

        TaskAttachment::query()->where('file_path', 'not like', 'attachments/%')->each(function (TaskAttachment $attachment) use (&$moved, &$missing) {
            // Legacy rows hold a public URL such as /storage/attachments/abc.pdf.
            $path = ltrim((string) preg_replace('#^.*?/storage/#', '', $attachment->file_path), '/');
            if (! str_starts_with($path, 'attachments/') || ! Storage::disk('public')->exists($path)) {
                $missing++;
                $this->warn("Attachment {$attachment->id}: file not found for {$attachment->file_path}");

                return;
            }
            Storage::disk(TaskAttachment::DISK)->put($path, Storage::disk('public')->get($path));
            $attachment->update(['file_path' => $path]);
            Storage::disk('public')->delete($path);
            $moved++;
        });

        $this->info("Moved {$moved} attachment(s); {$missing} could not be found.");

        return self::SUCCESS;
    }
}
