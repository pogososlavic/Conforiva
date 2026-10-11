@include('errors.page', ['status' => 500, 'reference' => request()->attributes->get('homya.error_reference')])
