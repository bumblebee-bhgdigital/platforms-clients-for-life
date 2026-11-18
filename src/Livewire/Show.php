<?php

/**
 * Detailseite einer Clients-for-Life-Instanz: Stammdaten, Knoten-Verknüpfung (verknüpfen/umhängen/lösen),
 * Aktivieren/Archivieren und leere Platzhalter-Bereiche für Beziehungsnetz, Touchpoints, Gesundheitscheck
 * und Jahresmeeting (folgen in eigenen Boards).
 */

namespace Platform\ClientsForLife\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\ClientsForLife\Enums\ClientsForLifeStatus;
use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Livewire\Concerns\LinksClientsToNodes;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\Core\Livewire\Concerns\HasPageContext;
use Platform\Organization\Models\OrganizationEntity;

class Show extends Component
{
    use HasPageContext, LinksClientsToNodes;

    public ClientsForLife $clientsForLife;

    public bool $showEdit = false;
    public array $form = [];

    public function mount(ClientsForLife $clientsForLife): void
    {
        Gate::authorize('view', $clientsForLife);
        $this->clientsForLife = $clientsForLife;
    }

    /**
     * Seitenkontext (Terminal/Kontext-Panel). Die Instanz hängt per HasEntityLinks an Knoten —
     * die App "Verknüpfungen" im Kontext-Panel ist damit automatisch verfügbar und nutzt dieselbe Bridge.
     */
    protected function pageContext(): ?Model
    {
        return $this->clientsForLife;
    }

    protected function pageContextOptions(): array
    {
        return [
            'subject' => $this->clientsForLife->title,
            'url' => route('clients-for-life.clients.show', $this->clientsForLife),
            'source' => 'clients-for-life.client.view',
            'capabilities' => ['manage_channels' => true, 'threads' => false],
            'meta' => [
                'status' => $this->clientsForLife->status?->value,
                'created_at' => $this->clientsForLife->created_at,
            ],
        ];
    }

    #[On('updateSidebar')]
    public function refreshClient(): void
    {
        $this->clientsForLife->refresh();
    }

    public function openEdit(): void
    {
        Gate::authorize('update', $this->clientsForLife);
        $c = $this->clientsForLife;
        $this->form = [
            'title' => $c->title,
            'description' => $c->description,
        ];
        $this->resetValidation();
        $this->showEdit = true;
    }

    public function save(): void
    {
        Gate::authorize('update', $this->clientsForLife);

        $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
        ]);

        try {
            $this->clientsForLife = app(ClientsForLifeService::class)->update($this->clientsForLife, $this->form);
        } catch (ClientsForLifeException $e) {
            $this->addError('form.title', $e->getMessage());

            return;
        }

        $this->showEdit = false;
        $this->dispatch('updateSidebar');
    }

    public function activate(): void
    {
        Gate::authorize('update', $this->clientsForLife);

        try {
            $this->clientsForLife = app(ClientsForLifeService::class)->activate($this->clientsForLife);
        } catch (ClientsForLifeException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->dispatch('updateSidebar');
    }

    public function archive(): void
    {
        Gate::authorize('update', $this->clientsForLife);

        $this->clientsForLife = app(ClientsForLifeService::class)->archive($this->clientsForLife);
        $this->dispatch('updateSidebar');
    }

    public function deleteClient()
    {
        Gate::authorize('delete', $this->clientsForLife);

        app(ClientsForLifeService::class)->delete($this->clientsForLife);
        $this->dispatch('updateSidebar');

        return $this->redirect(route('clients-for-life.dashboard'), navigate: true);
    }

    protected function afterLinkChange(): void
    {
        $this->cancelLinking();
        $this->clientsForLife->refresh();
        $this->dispatch('updateSidebar');
    }

    public function render()
    {
        $client = $this->clientsForLife;

        $nodeIds = app(ClientsForLifeService::class)->nodeIds($client);
        $nodes = $nodeIds === [] ? collect() : OrganizationEntity::with('type:id,name')->whereIn('id', $nodeIds)->get();

        return view('clients-for-life::livewire.show', [
            'client' => $client->loadMissing(['owner:id,name', 'responsibleAgent:id,name', 'template:id,name']),
            'nodes' => $nodes,
            'linkCandidates' => $this->linkCandidates(),
        ])->layout('platform::layouts.app');
    }
}
