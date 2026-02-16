<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'name_bn' => $this->name_bn,
            'nid_number' => $this->nid_number,
            'phone' => $this->phone,
            'dob' => $this->dob?->format('Y-m-d'),
            'gender' => $this->gender,
            'religion' => $this->religion,
            'blood_group' => $this->blood_group,
            'marital_status' => $this->marital_status,
            'place_of_birth' => $this->place_of_birth,
            'height' => $this->height,
            'passport' => $this->passport,
            'birth_reg' => $this->birth_reg,
            'profile_picture' => $this->profile_picture,
            'nid_file_path' => $this->nid_file_path,
            'birth_file_path' => $this->birth_file_path,
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'cadre_type' => $this->cadre_type,
            'batch_no' => $this->batch_no,
            'is_verified' => $this->is_verified,
            'status' => $this->status,
            'released_at' => $this->released_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Computed fields
            'current_salary' => $this->designation ? (float) ($this->designation->salary_min ?? 0) : 0,
            'can_add_spouse' => $this->canAddSpouse(),
            'max_spouses' => $this->getMaxSpouses(),
            'active_spouse_count' => $this->activeSpouses()->count(),
            
            // Relationships
            'designation' => $this->whenLoaded('designation', function () {
                return [
                    'id' => $this->designation->id,
                    'title' => $this->designation->title,
                    'title_bn' => $this->designation->title_bn,
                    'grade' => $this->designation->grade,
                    'salary_min' => $this->designation->salary_min,
                    'salary_max' => $this->designation->salary_max,
                    'salary_range' => $this->designation->salary_range,
                ];
            }),
            
            'office' => $this->whenLoaded('office', function () {
                return [
                    'id' => $this->office->id,
                    'name' => $this->office->name,
                    'code' => $this->office->code,
                    'location' => $this->office->location,
                ];
            }),
            
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'email' => $this->user->email,
                    'role' => $this->user->role,
                    'is_active' => $this->user->is_active,
                ] : null;
            }),
            
            'family' => $this->whenLoaded('family'),
            'addresses' => $this->whenLoaded('addresses'),
            'academics' => $this->whenLoaded('academics'),
            'transfers' => $this->whenLoaded('transfers'),
            'promotions' => $this->whenLoaded('promotions'),
        ];
    }
}