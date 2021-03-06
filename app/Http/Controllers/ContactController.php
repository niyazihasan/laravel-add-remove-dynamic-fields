<?php

namespace App\Http\Controllers;

use Illuminate\Http\{RedirectResponse, JsonResponse, Request};
use Illuminate\View\View;
use App\{Tag, Contact};
use App\Http\Requests\ContactForm;

class ContactController extends Controller
{
    public function __construct()
    {
        $this->middleware('prevent.back.history');
    }

    protected function index(): View
    {
        //$contacts = Contact::all();
        $contacts = Contact::getContacts();
        return view('contacts.index', compact('contacts'));
    }

    protected function create(): View
    {
        $tags = Tag::pluck('name', 'id');
        return view('contacts.create', compact('tags'));
    }

    protected function store(ContactForm $form): JsonResponse
    {
        if ($form->profile_image) {
            $image = $this->uploadImageGetName();
        }
        $contact = Contact::create(['email' => request('email'), 'profile_image' => $image ?? null]);
        $contact->addTel($contact, request('telephone_number'));
        $contact->tags()->sync(request('tags_id'));
        session()->flash('success', 'You have successfully created a contact.');

        return response()->json(['route' => route('contacts.index')]);
    }

    protected function show(Contact $contact): View
    {
        return view('contacts.show', compact('contact'));
    }

    protected function edit(Contact $contact): View
    {
        $tags = Tag::pluck('name', 'id');
        return view('contacts.edit', compact('contact', 'tags'));
    }

    protected function update(ContactForm $form, Contact $contact): JsonResponse
    {
        if ($form->profile_image) {
            $image = $this->uploadImageGetName();
            if($contact->profile_image) {
                $image_path = public_path("images/$contact->profile_image");
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
        }

        $contact->update(['email' => request('email'), 'profile_image' => $image ?? $contact->profile_image]);
        $contact->tags()->sync(request('tags_id'));
        $contact->updateTels($contact, request('telephone_number'));
        session()->flash('success', 'You have successfully edited a contact.');

        return response()->json(['route' => route('contacts.index')]);
    }

    protected function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        if ($contact->profile_image) {
            $image_path = public_path("images/$contact->profile_image");
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        return back()->withSuccess('Successfully deleted contact.');
    }

    private function uploadImageGetName(): string
    {
        $image = request('profile_image');
        $name = Contact::generateUniqueImageName() . '.' . $image->extension();
        $image->move(public_path('images'), $name);

        return $name;
    }

    protected function emailExists(Request $request): JsonResponse
    {
        $id = $request->get('id');
        $email = $request->get('email');
        if ($id) {
            $contact = Contact::findOrFail($id);
            if ($contact and $contact->email == $email) {
                return response()->json(true);
            }
        }
        $contact = Contact::where('email', $email)->first();
        $contact ? $res = false : $res = true;

        return response()->json($res);
    }

    protected function sortTable(Request $request): JsonResponse
    {
        $column = $request->get('column');
        $sort = $request->get('sort');
        $contacts = Contact::sort($column, $sort);
        return response()->json([
             'view' => view('contacts.ajax-table-sort', compact('contacts'))->render()
        ]);
    }

}
