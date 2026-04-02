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
            'avatar' => 'avatars/300-6.jpg', 
            'email' => $this->email, 
            'position' => 'Admin Staff', 
            'role' => $this->hasRole('admin') ? 'Administrator' : 'Admin Staff', 
            'last_login' => null, 
            'two_steps' => false, 
            'joined_day' => $this->created_at?->format('d M Y'), 
            'online' => false, 
            'initials' => [ 
                'label' => strtoupper(substr((string) $this->name, 0, 1)), 
                'state' => 'primary', 
            ], 
        ]; 
    } 
}
