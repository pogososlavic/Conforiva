@include('errors.page', ['status' => 404, 'reference' => request()->attributes->get('homya.error_reference')])
