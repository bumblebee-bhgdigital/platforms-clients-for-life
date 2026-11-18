<?php

/**
 * Übersichtsseite: alle Clients-for-Life-Instanzen des Teams (Team-Familie, nur sichtbare), mit Statusfilter,
 * Knoten-Anzeige und Anlegen (optional aus einer Vorlage). Route-Name bleibt clients-for-life.dashboard (Modul-Navigation).
 */

namespace Platform\ClientsForLife\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\ClientsForLife\Enums\ClientsForLifeStatus;
use Platform\ClientsForLife\Exceptions\ClientsForLifeException;
use Platform\ClientsForLife\Livewire\Concerns\ResolvesTeamFamily;
use Platform\ClientsForLife\Models\ClientsForLife;
use Platform\ClientsForLife\Models\ClientsForLifeTemplate;
use Platform\ClientsForLife\Services\ClientsForLifeService;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;

class Dashboard extends Component
{
    use ResolvesTeamFamily;

    /** '' = alle Status */
    public string $statusFilter = '';

    public bool $showCreate = false;
    public array $form = [
        'title' => '',
        'description' => '',
        'template_id' => null,
    ];

    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Clients for Life',
            'description' => 'Übersicht aller Clients-for-Life-Instanzen',
            'url' => route('clients-for-life.dashboard'),
            'source' => 'clients-for-life.dashboard',
            'recipients' => [],
            'meta' => ['view_type' => 'dashboard'],
        ]);
    }

    #[On('updateSidebar')]
    public function refreshList(): void
    {
    }

    public function openCreate(): void
    {
        Gate::authorize('create', ClientsForLife::class);
        $this->reset('form');
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function create()
    {
        Gate::authorize('create', ClientsForLife::class);

        $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.template_id' => 'nullable|integer',
        ]);

        $templateId = $this->form['template_id'] ? (int) $this->form['template_id'] : null;
        if ($templateId) {
            // Nur Vorlagen der eigenen Team-Familie, die der User sehen darf.
            Gate::authorize('view', ClientsForLifeTemplate::findOrFail($templateId));
        }

        try {
            $client = app(ClientsForLifeService::class)->create(
                Auth::user(),
                Auth::user()->currentTeam,
                ['title' => $this->form['title'], 'description' => $this->form['description']],
                null,
                $templateId,
            );
        } catch (ClientsForLifeException $e) {
            $this->addError('form.title', $e->getMessage());

            return null;
        }

        $this->showCreate = false;
        $this->dispatch('updateSidebar');

        return $this->redirect(route('clients-for-life.clients.show', $client), navigate: true);
    }

    public function render()
    {
        $user = Auth::user();
        $teamIds = $this->relevantTeamIds($user);

        $all = ClientsForLife::query()
            ->with(['owner:id,name', 'team:id,name', 'template:id,name'])
            ->whereIn('team_id', $teamIds)
            ->visibleTo($user)
            ->orderBy('title')
            ->get();

        // Knoten je Instanz (eine Abfrage)
        $nodesByClient = [];
        $links = EntityDimensionBridge::linksForLinkables([ClientsForLife::MORPH_ALIAS], $all->pluck('id')->all(), false);
        $entityNames = OrganizationEntity::whereIn('id', $links->pluck('entity_id')->filter()->unique()->all())->pluck('name', 'id');
        foreach ($links as $link) {
            if ($link->entity_id) {
                $nodesByClient[$link->linkable_id][] = ['id' => (int) $link->entity_id, 'name' => $entityNames[$link->entity_id] ?? '—'];
            }
        }

        $clients = $this->statusFilter !== ''
            ? $all->filter(fn ($c) => $c->status->value === $this->statusFilter)->values()
            : $all;

        $stats = [
            'total' => $all->count(),
            'active' => $all->where('status', ClientsForLifeStatus::ACTIVE)->count(),
            'draft' => $all->where('status', ClientsForLifeStatus::DRAFT)->count(),
            'unlinked' => $all->filter(fn ($c) => empty($nodesByClient[$c->id]))->count(),
        ];

        $templateOptions = ClientsForLifeTemplate::query()
            ->whereIn('team_id', $teamIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])
            ->all();

        return view('clients-for-life::livewire.dashboard', [
            'clients' => $clients,
            'nodesByClient' => $nodesByClient,
            'stats' => $stats,
            'multiTeam' => count($teamIds) > 1,
            'statusOptions' => array_map(fn (ClientsForLifeStatus $s) => ['value' => $s->value, 'label' => $s->label()], ClientsForLifeStatus::cases()),
            'templateOptions' => $templateOptions,
        ])->layout('platform::layouts.app');
    }
}
