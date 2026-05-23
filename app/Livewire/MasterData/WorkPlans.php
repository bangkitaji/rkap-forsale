<?php
namespace App\Livewire\MasterData;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\WorkPlan;
use Illuminate\Validation\Rule;

class WorkPlans extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $search = '';
    public $workPlanId = null;
    public $code = '';
    public $title = '';
    
    public $isEditMode = false;
    public $isModalOpen = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    protected function rules()
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('work_plans', 'code')->ignore($this->workPlanId),
            ],
            'title' => 'required|string|max:255',
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
            $workPlan = WorkPlan::findOrFail($id);
            $this->workPlanId = $workPlan->id;
            $this->code = $workPlan->code;
            $this->title = $workPlan->title;
            $this->isModalOpen = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Work Plan not found.');
        }
    }

    public function store()
    {
        $this->validate();

        try {
            WorkPlan::updateOrCreate(
                ['id' => $this->workPlanId],
                [
                    'code' => $this->code,
                    'title' => $this->title,
                ]
            );

            session()->flash('message', $this->workPlanId ? 'Work Plan updated successfully.' : 'Work Plan created successfully.');
            $this->closeModal();
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while saving the Work Plan.');
        }
    }

    public function delete($id)
    {
        try {
            WorkPlan::findOrFail($id)->delete();
            session()->flash('message', 'Work Plan deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Unable to delete Work Plan.');
        }
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetInputFields();
    }

    private function resetInputFields()
    {
        $this->workPlanId = null;
        $this->code = '';
        $this->title = '';
        $this->resetValidation();
    }

    public function render()
    {
        $workPlans = WorkPlan::when($this->search, function($query) {
                $query->where('code', 'like', '%' . $this->search . '%')
                      ->orWhere('title', 'like', '%' . $this->search . '%');
            })
            ->orderBy('code')
            ->paginate(10);

        return view('livewire.master-data.work-plans', [
            'workPlans' => $workPlans
        ])->layout('layouts.contentNavbarLayout');
    }
}
