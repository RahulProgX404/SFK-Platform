<?php

declare(strict_types=1);

namespace Modules\Settings\Filament\Resources\Users\Pages\Concerns;

use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Livewire\Attributes\Url;
use Modules\Settings\Filament\Resources\Users\Schemas\UserForm;

/**
 * ユーザーの登録 / 編集 laid out like プロフィール (core::filament.profile, design_ref/profile-page.png): the 設定 menu of the
 * form's parts on the left (UserForm::PANES), the chosen part on the right with キャンセル on the left and the save
 * button on the right under it (settings::pages.user-form). The parts switch in the browser; the whole form is still
 * validated and saved at once, and a part with an error opens itself.
 */
trait HasUserFormPanes
{
    /** The open part (UserForm::PANES), kept in the address as ?tab= like プロフィール. */
    #[Url(as: 'tab')]
    public string $pane = 'account';

    /** Livewire calls this on mount, after the address has set `pane`. */
    public function mountHasUserFormPanes(): void
    {
        if (! array_key_exists($this->pane, UserForm::PANES)) {
            $this->pane = (string) array_key_first(UserForm::PANES);
        }
    }

    public function getView(): string
    {
        return 'settings::pages.user-form';
    }

    public function form(Schema $schema): Schema
    {
        return UserForm::configure($schema, panes: true);
    }

    public function getFormActionsAlignment(): Alignment
    {
        return Alignment::Between;
    }
}
