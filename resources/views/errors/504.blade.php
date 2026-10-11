@include('errors.page', ['status' => 504, 'reference' => request()->attributes->get('homya.error_reference')])
