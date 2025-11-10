import os, sys
BASE_DIR = os.path.dirname(__file__)
sys.path.insert(0, BASE_DIR)

from wsgi_app import application  # Flask WSGI app
