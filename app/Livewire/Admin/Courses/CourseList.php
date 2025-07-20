<?php

namespace App\Livewire\Admin\Courses;

use Livewire\Component;
use App\Models\Course;

class CourseList extends Component
{
    public $search = '';
    public $courses = [];

    protected $listeners = ['openCourseSettings' => 'loadCourses',
                            'refreshCourses' => 'loadCourses'];

    public function mount()
    {
        $this->loadCourses();
    }

    public function updatedSearch()
    {
        $this->loadCourses();
    }

    public function loadCourses()
    {
        $this->courses = Course::with('tutor')
            ->where('title', 'like', '%' . $this->search . '%')
            ->orderBy('start_time', 'desc')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.courses.course-list')->layout('layouts.master');
    } 
}
