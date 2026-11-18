<?php

/**
 * Vorlagen für Clients-for-Life-Instanzen (z. B. Standard, Key Account): Liste, Anlegen, Bearbeiten, Löschen.
 * Eigener Navigationsbereich; Vorlagen sind teamweit und nicht an Kunden-Knoten verortet.
 */

namespace Platform\ClientsForLife\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Livewire\Concerns\ResolvesTeamFamily;
use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\ClientsForLife\Services\ClientsForLifeService;

class Templates extends Component
{
    use ResolvesTeamFamily;

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = ['name' => '', 'description' => ''];

    public function openCreate(): void
    {
        Gate::authorize('create', ClientsForLifeTemplate::class);
        $this->editingId = null;
        $this->form = ['name' => '', 'description' => ''];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $template = $this->visibleTemplate($id);
        Gate::authorize('update', $template);
        $this->editingId = $template->id;
        $this->form = ['name' => $template->name, 'description' => (string) $template->description];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.description' => 'nullable|string',
        ]);

        $service = app(ClientsForLifeService::class);

        try {
            if ($this->editingId) {
                $template = $this->visibleTemplate($this->editingId);
                Gate::authorize('update', $template);
                $service->updateTemplate($template, $this->form);
            } else {
                Gate::authorize('create', ClientsForLifeTemplate::class);
                $service->createTemplate(Auth::user(), Auth::user()->currentTeam, $this->form);
            }
        } catch (ClientsForLifeException $e) {
            $this->addError('form.name', $e->getMessage());

            return;
        }

        $this->showForm = false;
    }

    public function deleteTemplate(int $id): void
    {
        $template = $this->visibleTemplate($id);
        Gate::authorize('delete', $template);
        app(ClientsForLifeService::class)->deleteTemplate($template);
    }

    protected function visibleTemplate(int $id): ClientsForLifeTemplate
    {
        return ClientsForLifeTemplate::query()
            ->whereIn('team_id', $this->relevantTeamIds(Auth::user()))
            ->findOrFail($id);
    }

    public function render()
    {
        $templates = ClientsForLifeTemplate::query()
            ->withCount('clients')
            ->whereIn('team_id', $this->relevantTeamIds(Auth::user()))
            ->orderBy('name')
            ->get();

        return view('clients-for-life::livewire.templates', [
            'templates' => $templates,
        ])->layout('platform::layouts.app');
    }
}
