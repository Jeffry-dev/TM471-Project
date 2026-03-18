<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    // index endpoint that returns a paginated list of contact messages, with optional query parameters for pagination (page and perPage). If pagination parameters are not provided, return all contact messages.
    public function index(Request $request)
    {
        $data = $request->validate([
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        if (array_key_exists('perPage', $data)) {
            return ContactMessage::query()
                ->orderByDesc('created_at')
                ->paginate($data['perPage'])
                ->through(fn (ContactMessage $msg) => $this->toApi($msg));
        }

        return ContactMessage::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ContactMessage $msg) => $this->toApi($msg))
            ->all();
    }
    // store endpoint that creates a new contact message with the provided details. Validate the input data and return the created message in the response. If validation fails, return a 400 error with details about the validation errors.
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:200', 'required_if:preferredContact,email', 'required_without:phone'],
            'subject' => ['required', 'string', 'max:200'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40', 'required_if:preferredContact,phone', 'required_without:email'],
            'message' => ['required', 'string', 'max:1000'],
            'mode' => ['sometimes', 'in:contact,reservation'],
            'date' => ['sometimes', 'nullable', 'string'],
            'occasion' => ['sometimes', 'nullable', 'string'],
            'seating' => ['sometimes', 'nullable', 'string'],
            'preferredContact' => ['sometimes', 'in:email,phone'],
        ]);

        $msg = ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? '',
            'subject' => $data['subject'],
            'phone' => $data['phone'] ?? '',
            'message' => $data['message'],
            'mode' => $data['mode'] ?? 'contact',
            'date' => $data['date'] ?? null,
            'occasion' => $data['occasion'] ?? null,
            'seating' => $data['seating'] ?? null,
            'preferred_contact' => $data['preferredContact'] ?? (! empty($data['phone']) && empty($data['email']) ? 'phone' : 'email'),
        ]);

        return response()->json($this->toApi($msg), 201);
    }
    // toApi function that converts a ContactMessage model instance to an array suitable for API responses, with the appropriate fields and formatting.
    private function toApi(ContactMessage $msg): array
    {
        return [
            'id' => $msg->id,
            'name' => $msg->name,
            'email' => $msg->email,
            'subject' => $msg->subject,
            'phone' => $msg->phone,
            'message' => $msg->message,
            'mode' => $msg->mode,
            'date' => $msg->date,
            'occasion' => $msg->occasion,
            'seating' => $msg->seating,
            'preferredContact' => $msg->preferred_contact,
            'createdAt' => $msg->created_at?->toISOString(),
            'updatedAt' => $msg->updated_at?->toISOString(),
        ];
    }
}
