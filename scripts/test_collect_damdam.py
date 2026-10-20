"""Offline integrity tests; fixtures never enter HOMYA's public catalogue."""
import json
import io
from email.message import Message
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import collect_damdam as collector


class FixtureSourceHandler(collector.urllib.request.BaseHandler):
    """Exercise urllib's real redirect processing without opening sockets."""
    handler_order = 100
    def __init__(self, destinations):
        self.destinations = destinations
        self.contacted = []

    def https_open(self, request):
        self.contacted.append(request.full_url)
        status, location = self.destinations[request.full_url]
        headers = Message()
        headers['Content-Type'] = 'text/html'
        if location:
            headers['Location'] = location
        response = collector.urllib.response.addinfourl(io.BytesIO(b'fixture'), headers,
                                                       request.full_url, status)
        response.msg = 'fixture'
        return response


class CatalogueCollectorTest(unittest.TestCase):
    def setUp(self):
        network = patch('socket.create_connection', side_effect=AssertionError('Network forbidden'))
        network.start()
        self.addCleanup(network.stop)

    def test_redirects_validate_every_hop_before_contact(self):
        start = 'https://www.damdam.fr/start'
        middle = 'https://www.damdam.fr/middle'
        for target in ('https://127.0.0.1/private', 'http://www.damdam.fr/private',
                       'https://unapproved.example/private',
                       'https://www.damdam.fr:8443/private',
                       'https://user:password@www.damdam.fr/private'):
            with self.subTest(target=target):
                transport = FixtureSourceHandler({start: (302, middle), middle: (302, target)})
                build_opener = collector.urllib.request.build_opener
                with patch.object(collector.urllib.request, 'build_opener',
                                  side_effect=lambda handler: build_opener(transport, handler)), \
                        patch.object(collector.time, 'sleep'):
                    with self.assertRaises(ValueError):
                        collector.fetch(start)
                self.assertEqual(transport.contacted, [start, middle] * 3)

    def test_authorized_relative_and_media_redirects_succeed(self):
        for start, location, final, media in (
                ('https://www.damdam.fr/start', '/final', 'https://www.damdam.fr/final', False),
                ('https://damdam.b-cdn.net/start', 'https://res.cloudinary.com/final',
                 'https://res.cloudinary.com/final', True)):
            transport = FixtureSourceHandler({start: (302, location), final: (200, None)})
            build_opener = collector.urllib.request.build_opener
            with patch.object(collector.urllib.request, 'build_opener',
                              side_effect=lambda handler: build_opener(transport, handler)):
                self.assertEqual(collector.fetch(start, media), (b'fixture', 'text/html'))
            self.assertEqual(transport.contacted, [start, final])

    def product(self):
        return {'slug': 'fixture-produit', 'title': 'Produit de test', 'price': 12345,
                'sku': 'FIXTURE', 'stock': 999, 'reviews': [{'name': 'Do not retain'}],
                'images': [{'url': 'https://damdam.b-cdn.net/produits/fixture/1.jpg'},
                           {'url': 'https://unapproved.example/image.jpg'}],
                'shortDescription': 'Description technique. Livraison DAMDAM offerte.',
                'category': {'slug': 'froid-test', 'name': 'Froid'},
                'specs': [{'label': ' Largeur ', 'value': '20 cm'},
                          {'label': 'Largeur', 'value': '20 cm'},
                          {'label': 'Largeur', 'value': '30 cm'},
                          {'label': 'Absent', 'value': None}]}

    def test_flight_json_is_decoded_without_executing_source_script(self):
        payload = '0:{"products":'+json.dumps([self.product()])+'}'
        body = '<script>self.__next_f.push('+json.dumps([1, payload])+')</script>'
        values, _ = collector.payloads(body, 'products')
        self.assertEqual(values[0][0]['price'], 12345)

    def test_amounts_provenance_and_conflicts_are_preserved_without_source_sales_promises(self):
        record = collector.curate(self.product(), 'https://www.damdam.fr/produit/fixture-produit')
        self.assertEqual(record['price_cents'], 12345)
        self.assertEqual(record['description'], 'Description technique.')
        self.assertEqual(record['specifications'], [{'label': 'Largeur', 'value': '20 cm'}, {'label': 'Largeur', 'value': '30 cm'}])
        self.assertIn('conflicting_specification', record['issues'])
        self.assertEqual(len(record['images']), 1)
        self.assertNotIn('stock', record)
        self.assertNotIn('reviews', record)

    def test_unknown_or_invalid_prices_never_become_a_free_offer(self):
        for price in [None, 0, -1, 12.34, True, '12345']:
            product = self.product(); product['price'] = price
            record = collector.curate(product, 'https://www.damdam.fr/produit/fixture-produit')
            self.assertIsNone(record['price_cents'])
            self.assertIn('missing_price', record['issues'])

    def test_sitemap_listing_without_a_price_does_not_create_a_false_price_conflict(self):
        record = collector.curate(self.product(), 'https://www.damdam.fr/produit/fixture-produit', {'slug': 'fixture-produit'})
        self.assertNotIn('listing_price_changed', record['issues'])

    def test_unapproved_host_and_traversal_are_rejected(self):
        with patch('urllib.request.urlopen') as request:
            with self.assertRaises(ValueError): collector.fetch('https://unapproved.example/data')
            request.assert_not_called()
        product = self.product(); product['slug'] = '../private'
        with self.assertRaises(ValueError): collector.curate(product, 'https://www.damdam.fr/produit/invalid')

    def test_bioethanol_fireplaces_are_in_heating_even_when_source_prefix_is_furniture(self):
        self.assertEqual(collector.universe({'category': {'slug': 'mobilier-cheminees-au-bioethanol', 'name': 'Cheminées au bioéthanol'}}), 'heating')
        self.assertEqual(collector.universe({'category': {'slug': 'chauffage-granules-de-bois', 'name': 'Granulés de bois'}}), 'firewood')

    def test_http_200_without_a_product_does_not_create_a_fictitious_record(self):
        with tempfile.TemporaryDirectory(prefix='homya-collector-test-') as directory:
            with patch.object(collector, 'ARCHIVE', Path(directory)), patch.object(collector, 'fetch', return_value=(b'<title>Produit introuvable</title>', 'text/html')):
                record, error = collector.capture_detail({'slug': 'absent'})
                self.assertIsNone(record)
                self.assertIn('Missing product payload', error['error'])
                self.assertEqual(list(Path(directory).iterdir()), [])


if __name__ == '__main__':
    unittest.main()
