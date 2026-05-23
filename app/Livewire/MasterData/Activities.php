<?php
namespace App\Livewire\MasterData;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Activity;
use App\Models\WorkPlan;
use Illuminate\Validation\Rule;

class Activities extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search = '';
    public $activityId = null;
    public $work_plan_id = null;
    public $code = '';
    public $title = '';
    public $description = '';
    
    public $isEditMode = false;
    public $isModalOpen = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    protected function rules()
    {
        return [
            'work_plan_id' => 'required|exists:work_plans,id',
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('activities', 'code')->ignore($this->activityId),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];
    }

    public function create()
    {
        $this->resetInputFields();
        $this->isEditMode = false;
        $this->isModalOpen = true;
    }

    public function edit($id)
    {
        $this->resetInputFields();
        $this->isEditMode = true;
        
        try {
            $activity = Activity::findOrFail($id);
            $this->activityId = $activity->id;
            $this->work_plan_id = $activity->work_plan_id;
            $this->code = $activity->code;
            $this->title = $activity->title;
            $this->description = $activity->description;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Activity not found.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            Activity::updateOrCreate(
                ['id' => $this->activityId],
                [
                    'work_plan_id' => $this->work_plan_id,
                    'code' => $this->code,
                    'title' => $this->title,
                    'description' => $this->description,
                ]
            );

            session()->flash('message', $this->activityId ? 'Activity updated successfully.' : 'Activity created successfully.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while saving the Activity.');
        }
    }

    public function delete($id)
    {
        try {
            Activity::findOrFail($id)->delete();
            session()->flash('message', 'Activity deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete Activity.');
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->activityId = null;
        $this->work_plan_id = null;
        $this->code = '';
        $this->title = '';
        $this->description = '';
        $this->resetValidation();
    }

    public function render()
    {
        $activities = Activity::with('workPlan')
            ->when($this->search, function($query) {
                $query->where('code', 'like', '%' . $this->search . '%')
                      ->orWhere('title', 'like', '%' . $this->search . '%')
                      ->orWhereHas('workPlan', function($q) {
                          $q->where('title', 'like', '%' . $this->search . '%')
                            ->orWhere('code', 'like', '%' . $this->search . '%');
                      });
            })
            ->orderBy('code')
            ->paginate(10);

        return view('livewire.master-data.activities', [
            'activities' => $activities,
            'workPlans' => WorkPlan::orderBy('code')->get()
        ])->layout('layouts.contentNavbarLayout');
    }
}
