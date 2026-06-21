<?php  
 
namespace App\Http\Resources\Admin; 
 
use Illuminate\Http\Request; 
use Illuminate\Http\Resources\Json\JsonResource; 
 
class AdminStaffResource extends JsonResource 
{ 
    public function toArray(Request $request): array 
    { 
        return [ 
            'id' => $this->id, 
            'name' => $this->name, 
            'email' => $this->email, 
            'display_name' => $this->display_name, 
            'mobile_number' => $this->mobile_number, 
            'organization_id' => $this->organization_id, 
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->pluck('name')->values()->all()), 
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(), 
            'status' => $this->deleted_at ? 'inactive' : 'active', 
            'email_verified_at' => $this->email_verified_at?->toISOString(), 
            'mobile_verified_at' => $this->mobile_verified_at?->toISOString(), 
            'created_at' => $this->created_at?->toISOString(), 
            'updated_at' => $this->updated_at?->toISOString(), 
        ]; 
    } 
}
